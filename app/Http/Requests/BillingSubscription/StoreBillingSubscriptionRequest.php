<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Http\Requests\BillingSubscription;

use App\Http\Requests\Request;
use App\Models\BillingSubscription;

class StoreBillingSubscriptionRequest extends Request
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return auth()->user()->can('create', BillingSubscription::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'user_id' => ['sometimes'],
            'product_id' => ['sometimes'],
            'assigned_user_id' => ['sometimes'],
            'company_id' => ['sometimes'],
            'is_recurring' => ['sometimes'],
            'frequency_id' => ['sometimes'],
            'auto_bill' => ['sometimes'],
            'promo_code' => ['sometimes'],
            'promo_discount' => ['sometimes'],
            'is_amount_discount' => ['sometimes'],
            'allow_cancellation' => ['sometimes'],
            'per_set_enabled' => ['sometimes'],
            'min_seats_limit' => ['sometimes'],
            'max_seats_limit' => ['sometimes'],
            'trial_enabled' => ['sometimes'],
            'trial_duration' => ['sometimes'],
            'allow_query_overrides' => ['sometimes'],
            'allow_plan_changes' => ['sometimes'],
            'plan_map' => ['sometimes'],
            'refund_period' => ['sometimes'],
            'webhook_configuration' => ['sometimes'],
        ];
    }
}
