<?php

declare(strict_types=1);

namespace App\Domains\Markets\Requests;

use App\Domains\Markets\Models\Market;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class GrantMarketAccessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('grantAccess', $this->route('market'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Market $market */
        $market = $this->route('market');

        return [
            'user_id' => [
                'required',
                'integer',
                'exists:users,id',
                Rule::unique('market_user', 'user_id')->where('market_id', $market->id),
            ],
        ];
    }
}
