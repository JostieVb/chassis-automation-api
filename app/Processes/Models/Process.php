<?php

namespace App\Processes\Models;

use Illuminate\Database\Eloquent\Model;

class Process extends Model
{
    protected $table = 'process';

//    protected $casts = [
//        'process_xml' => 'string'
//    ];

//    public function getProcessXmlAttribute($value) {
//        return $this->getOriginal('process_xml');
//    }

    public function getProcessJsonAttribute($value) {
        return json_decode(json_encode($this->getOriginal('process_json')));
    }

    public function getPropertiesAttribute($value) {
        return json_decode(json_encode($this->getOriginal('properties')));
    }
}