<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class GlobalHeroChange extends Model
{
  protected $table = 'global_hero_change';
  protected $primaryKey = 'global_hero_change_id';
  public $timestamps = false;
  protected $connection= 'mysql_cache';

}
