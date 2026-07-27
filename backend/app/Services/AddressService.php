<?php

namespace App\Services;

use App\Models\Address;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AddressService
{
    /**
     * Create an address for the user, enforcing business rule 9: exactly one
     * is_default = true per user at all times. The first address a user adds
     * is always forced to be their default.
     */
    public function create(User $user, array $data): Address
    {
        return DB::transaction(function () use ($user, $data) {
            $isFirstAddress = ! $user->addresses()->exists();
            $wantsDefault = $data['is_default'] ?? false;

            if ($wantsDefault || $isFirstAddress) {
                $user->addresses()->update(['is_default' => false]);
                $data['is_default'] = true;
            } else {
                // Explicit, so the in-memory model reflects the real value immediately
                // instead of relying on the DB column default (which create() won't
                // hydrate back without an extra fresh() call).
                $data['is_default'] = false;
            }

            return $user->addresses()->create($data);
        });
    }

    /**
     * Update an address, unsetting any other default for the same user
     * if this one is being promoted to default.
     */
    public function update(Address $address, array $data): Address
    {
        return DB::transaction(function () use ($address, $data) {
            if ($data['is_default'] ?? false) {
                $address->user->addresses()
                    ->whereKeyNot($address->getKey())
                    ->update(['is_default' => false]);
            }

            $address->update($data);

            return $address->fresh();
        });
    }

    /**
     * Delete an address. If it was the default, promote another address
     * (if any remain) so the user is never left without a default.
     */
    public function delete(Address $address): void
    {
        DB::transaction(function () use ($address) {
            $wasDefault = $address->is_default;
            $userId = $address->user_id;

            $address->delete();

            if ($wasDefault) {
                Address::where('user_id', $userId)->first()?->update(['is_default' => true]);
            }
        });
    }
}
