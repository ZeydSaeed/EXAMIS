<?php

namespace App\Auth;

use App\Enums\AccountType;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Resolves accounts of one type only. Each guard has its own, so even a
 * valid session id cannot turn a student into staff or the other way round.
 */
class AccountTypeUserProvider extends EloquentUserProvider
{
    public function __construct(Hasher $hasher, string $model, private AccountType $accountType)
    {
        parent::__construct($hasher, $model);
    }

    /**
     * @param  Model|null  $model
     * @return Builder<Model>
     */
    protected function newModelQuery($model = null)
    {
        return parent::newModelQuery($model)->where('account_type', $this->accountType);
    }
}
