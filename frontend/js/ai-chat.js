// Floating "PC Builder Assistant" chat widget, shared across every page the same
// way the cart drawer is — injected once, toggled open/closed, backed by a single
// stateless /ai/chat endpoint (conversation history is kept client-side and resent).

const AI_CHAT_HISTORY_LIMIT = 12;
let aiChatHistory = [];
let aiChatSending = false;

function injectAiChatWidget() {
  if (document.getElementById("ai-chat-launcher")) return;

  const wrapper = document.createElement("div");
  wrapper.innerHTML = `
    <button type="button" class="ai-chat-launcher" id="ai-chat-launcher" aria-label="Open PC Builder Assistant">
      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path>
      </svg>
    </button>
    <div class="ai-chat-panel hidden" id="ai-chat-panel">
      <div class="ai-chat-header">
        <h3>PC Builder Assistant</h3>
        <button type="button" class="modal-close" id="ai-chat-close">&times;</button>
      </div>
      <div class="ai-chat-messages" id="ai-chat-messages"></div>
      <form class="ai-chat-input-row" id="ai-chat-form">
        <input type="text" id="ai-chat-input" placeholder="e.g. Build me a gaming PC for 30000 EGP" autocomplete="off" dir="auto" />
        <button type="submit" class="btn btn-auto" id="ai-chat-send">Send</button>
      </form>
    </div>
  `;
  document.body.appendChild(wrapper);

  document.getElementById("ai-chat-launcher").addEventListener("click", toggleAiChatPanel);
  document.getElementById("ai-chat-close").addEventListener("click", () => setAiChatPanelOpen(false));
  document.getElementById("ai-chat-form").addEventListener("submit", handleAiChatSubmit);

  appendAiChatMessage(
    "assistant",
    "Hi! Tell me your budget and what you'll use the PC for (gaming, editing, everyday use...) and I'll help you put a build together from what's actually in stock."
  );
}

function toggleAiChatPanel() {
  const panel = document.getElementById("ai-chat-panel");
  setAiChatPanelOpen(panel.classList.contains("hidden"));
}

function setAiChatPanelOpen(open) {
  const panel = document.getElementById("ai-chat-panel");
  panel.classList.toggle("hidden", !open);
  if (open) document.getElementById("ai-chat-input").focus();
}

// Renders the small formatting subset the assistant is instructed to use
// (**bold**, "- " list lines, blank-line paragraphs) — not general markdown.
// User messages always go through setAiChatBubbleText with plain=true instead,
// since user input must never be interpreted as markup.
function renderAiChatText(text) {
  const paragraphs = text.split(/\n{2,}/);

  return paragraphs
    .map((block) => {
      const lines = block.split("\n").map((line) => line.trim()).filter(Boolean);
      const isList = lines.length > 0 && lines.every((line) => line.startsWith("- "));

      if (isList) {
        const items = lines.map((line) => `<li>${boldify(escapeHtml(line.slice(2)))}</li>`).join("");
        return `<ul>${items}</ul>`;
      }

      return `<p>${lines.map((line) => boldify(escapeHtml(line))).join("<br>")}</p>`;
    })
    .join("");
}

function boldify(escapedText) {
  return escapedText.replace(/\*\*(.+?)\*\*/g, "<strong>$1</strong>");
}

function setAiChatBubbleText(bubble, text, plain) {
  if (plain) {
    bubble.textContent = text;
  } else {
    bubble.innerHTML = renderAiChatText(text);
  }
}

const AI_CHAT_COPY_ICON = `<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>`;
const AI_CHAT_COPIED_ICON = `<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>`;

function appendAiChatMessage(role, text) {
  const messagesEl = document.getElementById("ai-chat-messages");

  const wrap = document.createElement("div");
  wrap.className = `ai-chat-message-wrap ai-chat-message-wrap-${role}`;

  const bubble = document.createElement("div");
  bubble.className = `ai-chat-message ai-chat-message-${role}`;
  setAiChatBubbleText(bubble, text, role === "user");

  const copyBtn = document.createElement("button");
  copyBtn.type = "button";
  copyBtn.className = "ai-chat-copy-btn";
  copyBtn.setAttribute("aria-label", "Copy message");
  copyBtn.innerHTML = AI_CHAT_COPY_ICON;
  copyBtn.addEventListener("click", () => copyAiChatMessage(bubble, copyBtn));

  wrap.appendChild(bubble);
  wrap.appendChild(copyBtn);
  messagesEl.appendChild(wrap);
  messagesEl.scrollTop = messagesEl.scrollHeight;
  return bubble;
}

async function copyAiChatMessage(bubble, copyBtn) {
  try {
    await navigator.clipboard.writeText(bubble.textContent);
  } catch (error) {
    return;
  }

  copyBtn.innerHTML = AI_CHAT_COPIED_ICON;
  copyBtn.classList.add("copied");
  setTimeout(() => {
    copyBtn.innerHTML = AI_CHAT_COPY_ICON;
    copyBtn.classList.remove("copied");
  }, 1200);
}

async function handleAiChatSubmit(event) {
  event.preventDefault();
  if (aiChatSending) return;

  const input = document.getElementById("ai-chat-input");
  const message = input.value.trim();
  if (!message) return;

  input.value = "";
  appendAiChatMessage("user", message);

  const sendBtn = document.getElementById("ai-chat-send");
  aiChatSending = true;
  sendBtn.disabled = true;
  const thinkingBubble = appendAiChatMessage("assistant", "Thinking...");

  try {
    const result = await apiRequest("/ai/chat", {
      method: "POST",
      body: { message, history: aiChatHistory.slice(-AI_CHAT_HISTORY_LIMIT) },
    });

    setAiChatBubbleText(thinkingBubble, result.data.reply, false);
    aiChatHistory.push({ role: "user", text: message });
    aiChatHistory.push({ role: "assistant", text: result.data.reply });

    const action = result.data.action;
    if (action?.result?.success) {
      if (action.type === "add_to_cart" && typeof refreshCartBadge === "function") {
        refreshCartBadge();
      }
      if (typeof showToast === "function") {
        showToast(
          action.type === "create_build" ? "The assistant created a new build for you." : "The assistant updated your cart.",
          "success"
        );
      }
    }
  } catch (error) {
    setAiChatBubbleText(thinkingBubble, error.message || "Something went wrong — please try again.", true);
  } finally {
    aiChatSending = false;
    sendBtn.disabled = false;
  }
}

document.addEventListener("DOMContentLoaded", injectAiChatWidget);
