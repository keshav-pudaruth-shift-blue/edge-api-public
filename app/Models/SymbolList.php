<?php

namespace App\Models;

use GeneaLabs\LaravelModelCaching\Traits\Cachable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SymbolList extends BaseModel
{
    use HasFactory;
    use Cachable;

    public $table = 'symbol_list';

    protected $fillable = [
        'name',
        'company_name'
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'is_index' => 'boolean',
    ];

    public $primaryKey = 'name';

    public $incrementing = false;

    protected function name(): Attribute
    {
        return Attribute::make(function ($value) {
            //Cleanup names for index symbols
            if($this->is_index === true) {
                return str_replace('^', '', $value);
            } else {
                return $value;
            }
        });
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function optionContracts(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(OptionsContracts::class, 'symbol', 'name');
    }
}
