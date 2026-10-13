<?php

namespace App\Services\Client\Address;

use App\Models\Address;
use App\Models\Business;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;

class AddressService
{
    /**
     * @return Collection<int, Address>
     *
     * @throws AuthorizationException
     */
    public function list(User $user): Collection
    {
        return $this->businessFor($user)
            ->addresses()
            ->latest()
            ->get();
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws AuthorizationException
     */
    public function create(User $user, array $data): Address
    {
        return $this->businessFor($user)->addresses()->create($data);
    }

    /**
     * @throws AuthorizationException
     */
    public function find(User $user, int $id): Address
    {
        return $this->businessFor($user)->addresses()->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws AuthorizationException
     */
    public function update(User $user, int $id, array $data): Address
    {
        $address = $this->find($user, $id);
        $address->update($data);

        return $address;
    }

    /**
     * @throws AuthorizationException
     */
    public function delete(User $user, int $id): void
    {
        $this->find($user, $id)->delete();
    }

    /**
     * The authenticated user must be a business customer with an associated
     * business. Admins and users without a business_id are rejected.
     *
     * @throws AuthorizationException
     */
    private function businessFor(User $user): Business
    {
        if ($user->business_id === null) {
            throw new AuthorizationException('Your account is not associated with a business.');
        }

        return $user->business;
    }
}