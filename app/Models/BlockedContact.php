<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * An address or a whole domain that is never mailed again on this account.
 *
 * @property int $id
 * @property int $user_id
 * @property string|null $email
 * @property string|null $domain
 * @property string|null $reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'email', 'domain', 'reason'])]
class BlockedContact extends Model
{
    use BelongsToUser;
}
