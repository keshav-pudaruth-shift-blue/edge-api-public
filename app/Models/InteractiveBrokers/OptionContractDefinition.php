<?php

namespace App\Models\InteractiveBrokers;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class OptionContractDefinition extends BaseModel
{
    use HasFactory;

    protected $table = 'ib_option_contract_definitions';
}
