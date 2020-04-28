<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Report extends Model
{
    /**
     * Table name associated with
     *
     * @var $table
     */
    protected $table = 'reports';

    /**
     * Specify primary key
     *
     * @var $primaryKey
     */
    protected $primaryKey = 'id';

    /**
     * Specify increment true
     *
     * @var $incrementing
     */
    public $incrementing = true;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'report_type', 'object_id', 'unix_timestamp', 'latitude', 'longitude',
        'magnitude', 'city', 'county', 'state', 'source', 'tornado', 'funnelcloud',
        'hail', 'hailsize', 'phenom', 'significance', 'office', 'office_id', 'latlon',
        'prob_hail', 'prob_wind', 'prob_tor', 'remarks'
    ];


    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
    ];


    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
    ];
}
