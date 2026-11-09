<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** The logged-in teacher (Profile and Personal info screens). */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'name_km' => $this->name_km,
            'email' => $this->email,
            'phone' => $this->phone,
            'role' => $this->role,
            'locale' => $this->locale,
            'homeroom_class' => $this->whenLoaded('homeroomClasses', fn () => $this->homeroomClasses->first()?->only(['id', 'name'])),
        ];
    }
}
