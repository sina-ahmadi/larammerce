<?php

namespace App\Models;

use App\Models\Interfaces\TagContract as TagContract;
use App\Models\Traits\Taggable;

/**
 * @property integer id
 * @property string name
 * @property integer city_id
 *
 * @property City city
 * @property CustomerAddress[] customerAddresses
 *
 * Class District
 * @package App\Models
 */
class District extends BaseModel implements TagContract
{
    use Taggable;

    protected $table = 'districts';
    protected $fillable = [
        'name', 'city_id'
    ];
    public $timestamps = false;
    protected static array $SORTABLE_FIELDS = ['id', 'name'];
    protected static array $SEARCHABLE_FIELDS = ['name'];


    /*
     * Relations Methods
     */

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function city()
    {
        return $this->belongsTo('\\App\\Models\\City', 'city_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function customerAddresses()
    {
        return $this->hasMany('App\\Models\\CustomerAddress', 'city_id');
    }

    public function getText()
    {
        return $this->name;
    }

    public function getValue()
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getSearchUrl(): string
    {
        return '';
    }
}
