<?php

namespace App\Services;

use App\Models\Build;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class AiChatService
{
    // Keeps the context compact even if the catalog grows — a real project would
    // add a search/filter step here (by budget/category keywords in the message)
    // instead of always sending the whole active catalog.
    private const MAX_CATALOG_ITEMS = 500;

    private const VALID_SLOTS = ['cpu', 'motherboard', 'gpu', 'cooler', 'case', 'psu', 'ram', 'storage'];

    public function __construct(private BuildService $buildService, private CartService $cartService)
    {
    }

    /**
     * @param  array<int, array{role: string, text: string}>  $history
     * @return array{reply: string, action: ?array}
     */
    public function chat(?User $user, string $message, array $history): array
    {
        // Up to 3 retry attempts per callOpenRouter() call, each with its own 60s HTTP
        // timeout, and chat() can call it twice (initial + tool follow-up) — worst case
        // comfortably exceeds PHP's default 60s script execution limit, which kills the
        // request outright rather than letting our own retry/error handling run.
        set_time_limit(300);

        $catalog = $this->buildCatalogContext();
        $tools = $this->toolDeclarations();

        $messages = [['role' => 'system', 'content' => $this->buildSystemPrompt($catalog)]];
        $messages = array_merge($messages, $this->historyToMessages($history));
        $messages[] = ['role' => 'user', 'content' => $message];

        $response = $this->callOpenRouter($messages, $tools, $catalog);
        $toolCall = $this->extractToolCall($response);

        if (! $toolCall) {
            return ['reply' => $this->extractText($response), 'action' => null];
        }

        $result = $this->executeTool($user, $toolCall['name'], $toolCall['args']);

        // The assistant's tool_calls message (including its call id) must be replayed
        // verbatim, followed by a "tool" message carrying that same id and the result —
        // that's what lets the model phrase a natural-language confirmation next.
        $messages[] = $toolCall['raw_message'];
        $messages[] = [
            'role' => 'tool',
            'tool_call_id' => $toolCall['id'],
            'content' => json_encode($result),
        ];

        $followUp = $this->callOpenRouter($messages, $tools, $catalog);

        return [
            'reply' => $this->extractText($followUp) ?: ($result['message'] ?? 'Done.'),
            'action' => ['type' => $toolCall['name'], 'result' => $result],
        ];
    }

    /**
     * Only the fields a shopper (or the model) actually needs — never the whole
     * Eloquent row — kept compact so the prompt stays small regardless of how
     * many spec columns the product_specifications table has grown to.
     */
    private function buildCatalogContext(): array
    {
        $products = Product::query()
            ->active()
            ->with(['category', 'brand', 'specification'])
            ->orderBy('id')
            ->limit(self::MAX_CATALOG_ITEMS)
            ->get();

        return $products->map(function (Product $product) {
            $specs = [];

            if ($product->specification) {
                $specs = Arr::except($product->specification->toArray(), ['id', 'product_id', 'created_at', 'updated_at', 'custom_specifications']);
                $specs = array_filter($specs, fn ($value) => $value !== null && $value !== '');

                foreach ($product->specification->custom_specifications ?? [] as $pair) {
                    if (! empty($pair['key'])) {
                        $specs[$pair['key']] = $pair['value'];
                    }
                }
            }

            return [
                'id' => $product->id,
                'name' => $product->name,
                'category' => $product->category?->name,
                'brand' => $product->brand?->name,
                'price' => (float) ($product->sale_price ?? $product->price),
                'in_stock' => $product->stock > 0,
                'specs' => $specs,
            ];
        })->all();
    }

    private function buildSystemPrompt(array $catalog): string
    {
        $catalogJson = json_encode($catalog, JSON_UNESCAPED_UNICODE);

        return <<<PROMPT
            You are a PC-building expert assistant embedded in an online PC parts store. You have NO knowledge of any products, prices, or stores other than what is in the CATALOG JSON below — you do not know current market prices, other retailers (Tekzilla, MicroLess, Jumia, Amazon, etc.), or products that exist outside this store. Answer purely from CATALOG JSON, as if it were the only source of information you have ever seen.

            Hard rules:
            - All prices in the CATALOG JSON ("price" field) are in Egyptian Pounds (EGP) — never dollars. Always write prices as "EGP 1,234.56", never with a $ sign, regardless of what language you're replying in.
            - Only recommend, mention, or add to cart/build products that appear in the CATALOG JSON below. Never invent a product or use general knowledge about parts that aren't listed, and never cite prices from outside stores or a "current market". If the user asks for something not in the catalog, say plainly it isn't available in this store.
            - When assembling a full build, check compatibility using the "specs" fields: CPU and motherboard "socket" must match; RAM "ram_type" must match the motherboard's; a PSU's "wattage" should comfortably exceed the CPU+GPU "power_draw"; a GPU's "length_mm" must fit under the case's "max_gpu_length"; a cooler's "socket" list must include the CPU's socket.
            - This check must be the LAST thing you do, after the parts list is otherwise final — not just when you first pick parts. If you swap any part to fit the budget (a cheaper CPU, RAM, or motherboard), that swap can silently break a pairing you already checked, so re-verify all of it against the final list: write out "CPU socket: X, Motherboard socket: Y" and "RAM ram_type: X, Motherboard ram_type: Y" and confirm each pair is an exact string match before you present the build. Never present a build where you haven't done this final check on the parts you're actually about to list.
            - Respect the user's stated budget — don't exceed it unless they explicitly say it's flexible. Before replying, actually add up the prices of the parts you'd pick, step by step, and compare that sum to the budget: if total_cost <= budget, the build IS affordable and you say so (state the leftover amount); only say the budget is insufficient if total_cost > budget. Do not claim a budget is insufficient just because it doesn't exactly match the total — a lower total is a good thing.
            - This also applies when the user asks what they can get for a small amount, or about a single item instead of a full build: compare that amount directly against each product's "price" field as plain numbers. A product is affordable whenever its price is less than or equal to the stated amount. Do the subtraction explicitly (budget - price = leftover) before you answer, and double-check the sign: if price < budget, the leftover is positive and the item IS affordable — never say a smaller number "exceeds" or "is higher than" a larger one. For example, a product priced 79.99 against a budget of 500 is affordable with 420.01 left over, not the other way around.
            - Never open with a blanket claim like "nothing in the catalog fits this budget" or "no product at or below X" and then go on to name a specific product — if the sentence right after names a product, that product's price is your proof, so check it against the budget first. Concretely: go through the catalog items relevant to the request one by one, note each price next to the budget, and only after that comparison state your conclusion — the conclusion must not contradict the prices you just listed. List every in-budget product you found (not just the single cheapest one), not just a single example.
            - Never suggest or name a specific alternative product (by name, size, or capacity) that isn't literally a row in the CATALOG JSON, even as a hypothetical "you could consider X instead" — if no cheaper in-catalog alternative exists for a slot, just say so instead of inventing one.
            - Briefly explain why each part was chosen (price/performance fit, compatibility), not just a bare list.
            - If there's more than one reasonable option for a slot, mention up to 3 alternatives instead of just one.
            - Only in-stock products ("in_stock": true) can actually be purchased — mention if something relevant is out of stock.
            - Use the add_to_cart or create_build tool only when the user clearly asks you to add items to their cart or assemble/save a build — not just when discussing options. This applies the same way no matter what language the request is written in: an Arabic imperative like "ضيف ده للعربة" or "جهزلي تجميعة" is just as clear and actionable as the same request in English, and must trigger the tool call immediately, not a confirmation question, an offer, or a spec summary instead.

            Formatting (this renders in a small chat widget, not a document):
            - No markdown headers (#, ##, ###), no horizontal rules (---), no tables, no numbered lists, no code blocks.
            - You may use **bold** for product names, prices, and key terms.
            - For a list of parts, use a simple "- " at the start of each line.
            - Keep it short: brief paragraphs separated by a blank line, not long walls of text.

            CATALOG JSON:
            {$catalogJson}
            PROMPT;
    }

    private function toolDeclarations(): array
    {
        return [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'add_to_cart',
                    'description' => 'Add one or more catalog products to the current user\'s shopping cart.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'items' => [
                                'type' => 'array',
                                'items' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'product_id' => ['type' => 'integer'],
                                        'quantity' => ['type' => 'integer'],
                                    ],
                                    'required' => ['product_id'],
                                ],
                            ],
                        ],
                        'required' => ['items'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'create_build',
                    'description' => 'Create a new saved PC build for the user with the recommended parts assigned to their correct slots.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'name' => ['type' => 'string'],
                            'items' => [
                                'type' => 'array',
                                'items' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'product_id' => ['type' => 'integer'],
                                        'slot' => ['type' => 'string', 'enum' => self::VALID_SLOTS],
                                        'quantity' => [
                                            'type' => 'integer',
                                            'description' => 'Only meaningful for "ram" and "storage" slots, which support more than one item. Every other slot always holds exactly 1.',
                                        ],
                                    ],
                                    'required' => ['product_id', 'slot'],
                                ],
                            ],
                        ],
                        'required' => ['items'],
                    ],
                ],
            ],
        ];
    }

    private function executeTool(?User $user, string $name, array $args): array
    {
        if (! $user) {
            return ['success' => false, 'message' => 'The user needs to log in before this action can be performed.'];
        }

        return match ($name) {
            'add_to_cart' => $this->executeAddToCart($user, $args),
            'create_build' => $this->executeCreateBuild($user, $args),
            default => ['success' => false, 'message' => "Unknown action \"{$name}\"."],
        };
    }

    private function executeAddToCart(User $user, array $args): array
    {
        $added = [];
        $failed = [];

        foreach ($args['items'] ?? [] as $item) {
            $product = Product::find($item['product_id'] ?? null);

            if (! $product) {
                $failed[] = $item['product_id'] ?? null;

                continue;
            }

            try {
                $this->cartService->addItem($user, $product, max(1, (int) ($item['quantity'] ?? 1)));
                $added[] = $product->name;
            } catch (\Throwable $e) {
                $failed[] = $product->name;
            }
        }

        return [
            'success' => count($added) > 0,
            'added' => $added,
            'failed' => $failed,
            'message' => count($added) > 0
                ? 'Added to cart: '.implode(', ', $added).(count($failed) ? '. Could not add: '.implode(', ', $failed) : '')
                : 'Could not add any of those items to the cart.',
        ];
    }

    private function executeCreateBuild(User $user, array $args): array
    {
        $build = $this->buildService->create($user, $args['name'] ?? null);
        $addedSlots = [];
        $failed = [];

        foreach ($args['items'] ?? [] as $item) {
            $slot = $item['slot'] ?? null;
            $product = Product::find($item['product_id'] ?? null);

            if (! $product || ! in_array($slot, self::VALID_SLOTS, true)) {
                $failed[] = $item['product_id'] ?? null;

                continue;
            }

            try {
                $quantity = max(1, (int) ($item['quantity'] ?? 1));
                $this->buildService->addItem($build, $product, $slot, $quantity);
                $addedSlots[] = $quantity > 1 ? "{$slot}: {$product->name} x{$quantity}" : "{$slot}: {$product->name}";
            } catch (\Throwable $e) {
                $failed[] = $product->name;
            }
        }

        return [
            'success' => count($addedSlots) > 0,
            'build_id' => $build->id,
            'added' => $addedSlots,
            'failed' => $failed,
            'message' => count($addedSlots) > 0
                ? 'Build created with: '.implode(', ', $addedSlots)
                : 'Could not add any parts to the build.',
        ];
    }

    private function historyToMessages(array $history): array
    {
        return array_map(
            fn (array $turn) => [
                'role' => $turn['role'] === 'assistant' ? 'assistant' : 'user',
                'content' => $turn['text'],
            ],
            $history
        );
    }

    private function callOpenRouter(array $messages, array $tools, array $catalog = []): array
    {
        $apiKey = config('services.openrouter.key');
        $model = config('services.openrouter.model');

        if (! $apiKey) {
            throw new RuntimeException('OPENROUTER_API_KEY is not configured.');
        }

        // OpenRouter streams whitespace "keep-alive" padding while a free-tier model
        // cold-starts, before the real JSON payload. Occasionally the connection
        // completes with only that padding and no payload — retry a couple of times
        // rather than surfacing an empty reply to the user.
        $lastBody = null;

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            // Free-tier model queues can be noticeably slower than paid ones, especially
            // once the full catalog + tool definitions are in the prompt — 30s wasn't
            // enough under real load and was timing out on anything but trivial messages.
            $response = Http::timeout(60)
                ->withToken($apiKey)
                ->withHeaders([
                    // Recommended (not required) by OpenRouter to identify the app in their
                    // dashboard/analytics — harmless to send even for local development.
                    'HTTP-Referer' => config('app.url'),
                    'X-Title' => 'PC Builder',
                ])
                ->post('https://openrouter.ai/api/v1/chat/completions', [
                    'model' => $model,
                    'messages' => $messages,
                    'tools' => $tools,
                    // Low temperature on purpose: this task is grounded arithmetic/lookup
                    // over a fixed catalog, not creative writing — a high default temperature
                    // was producing inconsistent budget math and occasional invented products.
                    'temperature' => 0.2,
                ]);

            if ($response->failed()) {
                throw new RuntimeException('OpenRouter request failed: '.$response->body());
            }

            $json = $response->json();
            $lastBody = $response->body();
            $message = $json['choices'][0]['message'] ?? null;

            if (! $message) {
                continue;
            }

            // Free-tier ":free" models can be routed to different backend hosts per
            // request, some of which run a more heavily quantized copy that ignores
            // the system prompt and answers from general knowledge (wrong currency,
            // real-world store names, off-catalog parts) instead of the CATALOG JSON.
            // A plain-text reply (no tool call) that trips these tells is discarded
            // and retried, same as an empty/malformed response.
            if (empty($message['tool_calls'])) {
                $content = (string) ($message['content'] ?? '');

                // The model reliably applies the compatibility rule when it first picks parts,
                // but loses track of it after swapping a part to fit the budget — it doesn't
                // re-check the new part against what it already chose. Since we can't trust
                // that check, we do it ourselves against the real catalog and discard/retry
                // any reply that names two parts which don't actually fit together.
                if ($this->looksUngrounded($content) || $this->hasIncompatibleParts($content, $catalog) || $this->hasHallucinatedProduct($content, $catalog)) {
                    continue;
                }
            }

            return $json;
        }

        throw new RuntimeException('OpenRouter returned an empty/malformed response after retries: '.$lastBody);
    }

    /**
     * Cross-checks any catalog products named in a free-text reply against their real specs —
     * catches CPU/motherboard socket mismatches and RAM/motherboard ram_type mismatches the
     * model stated together, regardless of what the model itself claimed about compatibility.
     */
    private function hasIncompatibleParts(string $reply, array $catalog): bool
    {
        if ($reply === '' || ! $catalog) {
            return false;
        }

        $mentioned = array_values(array_filter($catalog, fn (array $item) => str_contains($reply, $item['name'])));

        $byCategory = fn (string $category) => array_values(array_filter($mentioned, fn (array $item) => $item['category'] === $category));

        $cpus = $byCategory('CPUs');
        $boards = $byCategory('Motherboards');
        $ram = $byCategory('Memory');

        if (count($cpus) === 1 && count($boards) === 1) {
            $cpuSocket = $cpus[0]['specs']['socket'] ?? null;
            $boardSocket = $boards[0]['specs']['socket'] ?? null;
            if ($cpuSocket && $boardSocket && $cpuSocket !== $boardSocket) {
                return true;
            }
        }

        if (count($ram) === 1 && count($boards) === 1) {
            $ramType = $ram[0]['specs']['ram_type'] ?? null;
            $boardRamType = $boards[0]['specs']['ram_type'] ?? null;
            if ($ramType && $boardRamType && $ramType !== $boardRamType) {
                return true;
            }
        }

        return false;
    }

    /**
     * The model reliably sticks to real catalog products in the main recommendation, but has
     * been observed inventing a plausible-sounding non-catalog product (e.g. a cheaper "you
     * could switch to X" suggestion) that was never in the CATALOG JSON. Rather than trust the
     * model's own "never invent a product" instruction, this scans for real-brand + model-number
     * patterns and rejects any that don't match a real catalog product name — deliberately
     * conservative (requires a digit in the model token) to avoid flagging plain brand mentions.
     */
    private function hasHallucinatedProduct(string $reply, array $catalog): bool
    {
        if ($reply === '' || ! $catalog) {
            return false;
        }

        $brands = array_unique(array_filter(array_column($catalog, 'brand')));
        $names = array_column($catalog, 'name');

        foreach ($brands as $brand) {
            if (! preg_match_all('/'.preg_quote($brand, '/').'\s+([A-Za-z0-9][\w.\-]*(?:\s+[A-Za-z0-9][\w.\-]*){0,4})/u', $reply, $matches)) {
                continue;
            }

            foreach ($matches[0] as $i => $fullMatch) {
                // Only treat it as a claimed product if a model-style token (containing a
                // digit) shows up in the words right after the brand — plain brand mentions
                // ("ASUS من أفضل الشركات") never trip this.
                if (! preg_match('/\d/', $matches[1][$i])) {
                    continue;
                }

                $candidate = trim($fullMatch);
                $isReal = false;

                foreach ($names as $name) {
                    if (str_starts_with($name, $candidate) || str_starts_with($candidate, $name)) {
                        $isReal = true;

                        break;
                    }
                }

                if (! $isReal) {
                    return true;
                }
            }
        }

        return false;
    }

    private function looksUngrounded(string $reply): bool
    {
        if ($reply === '') {
            return false;
        }

        // Both are things the system prompt explicitly forbids — seeing either is a
        // reliable sign the model answered from general knowledge, not the catalog.
        if (preg_match('/\$\s?\d/', $reply)) {
            return true;
        }

        $lower = mb_strtolower($reply);

        foreach (['tekzilla', 'microless', 'jumia', 'amazon'] as $offCatalogStore) {
            if (str_contains($lower, $offCatalogStore)) {
                return true;
            }
        }

        return false;
    }

    private function extractToolCall(array $response): ?array
    {
        $message = $response['choices'][0]['message'] ?? null;
        $call = $message['tool_calls'][0] ?? null;

        if (! $call) {
            return null;
        }

        return [
            'id' => $call['id'],
            'name' => $call['function']['name'],
            'args' => json_decode($call['function']['arguments'] ?? '{}', true) ?? [],
            'raw_message' => $message,
        ];
    }

    private function extractText(array $response): string
    {
        return trim((string) ($response['choices'][0]['message']['content'] ?? ''));
    }
}
