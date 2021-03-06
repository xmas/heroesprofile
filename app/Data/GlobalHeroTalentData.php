<?php
namespace App\Data;
use Illuminate\Support\Facades\DB;
use Session;

/*
use Illuminate\Support\Facades\DB;
use Cache;
use App\Battletag;
use App\LeagueBreakdown;
use App\LeagueTier;
use DateTime;
*/

class GlobalHeroTalentData
{
  private $hero;
  private $timeframe;
  private $game_versions;
  private $game_type;
  private $player_league_tier;
  private $hero_league_tier;
  private $role_league_tier;
  private $game_map;
  private $hero_level;
  private $mirror;
  private $region;

  public static function instance($hero, $timeframe, $game_type, $player_league_tier,
                               $hero_league_tier, $role_league_tier, $game_map, $hero_level,
                               $mirror, $region)
  {
    return new GlobalHeroTalentData($hero, $timeframe, $game_type, $player_league_tier,
                                 $hero_league_tier, $role_league_tier, $game_map, $hero_level,
                                 $mirror, $region);
  }


  public function __construct($hero, $timeframe, $game_type, $player_league_tier,
                               $hero_league_tier, $role_league_tier, $game_map, $hero_level,
                               $mirror, $region) {
    $this->timeframe = $timeframe;
    $this->game_type = $game_type;
    $this->hero = $hero;
    $this->player_league_tier = $player_league_tier;
    $this->hero_league_tier = $hero_league_tier;
    $this->role_league_tier = $role_league_tier;
    $this->game_map = $game_map;
    $this->hero_level = $hero_level;
    $this->mirror = $mirror;
    $this->region = $region;
  }

  private function getTopFiveBuilds($type){
    $this->game_versions = \GlobalFunctions::instance()->getGameVersionsFromFilter($this->timeframe);

    $limit = 5;

    if($type != "Popular"){
      $limit = 100;
    }

    $builds = \App\GlobalHeroTalents::Filters($this->hero, $this->game_versions, $this->game_type, $this->player_league_tier,
                                          $this->hero_league_tier, $this->role_league_tier, $this->game_map, $this->hero_level, $this->mirror, $this->region)
                   ->select('level_one', 'level_four', 'level_seven', 'level_ten', 'level_thirteen', 'level_sixteen', 'level_twenty', DB::raw('SUM(games_played) as games_played'))
                   ->where('level_twenty', '<>', '0')
                   ->groupBy('level_one', 'level_four', 'level_seven', 'level_ten', 'level_thirteen', 'level_sixteen', 'level_twenty')
                   ->orderBy('games_played', 'DESC')
                   ->limit($limit)
                   ->get();

    $return_data = array();
    if($type != "Popular"){
      $dupe = array();
      $counter = 0;
      foreach($builds as $key => $value){
        if($type == "HP"){
          if(!in_array($value->level_one . "|" . $value->level_four . "|" . $value->level_seven, $dupe)){
            $dupe[$counter] = $value->level_one . "|" . $value->level_four . "|" . $value->level_seven;
            $return_data[$counter] = $value;
            $counter++;
          }
        }else if($type == "1"){
          if(!in_array($value->level_one, $dupe)){
            $dupe[$counter] = $value->level_one;
            $return_data[$counter] = $value;
            $counter++;
          }
        }else if($type == "4"){
          if(!in_array($value->level_four, $dupe)){
            $dupe[$counter] = $value->level_four;
            $return_data[$counter] = $value;
            $counter++;
          }
        }else if($type == "7"){
          if(!in_array($value->level_seven, $dupe)){
            $dupe[$counter] = $value->level_seven;
            $return_data[$counter] = $value;
            $counter++;
          }
        }else if($type == "10"){
          if(!in_array($value->level_ten, $dupe)){
            $dupe[$counter] = $value->level_ten;
            $return_data[$counter] = $value;
            $counter++;
          }
        }else if($type == "13"){
          if(!in_array($value->level_thirteen, $dupe)){
            $dupe[$counter] = $value->level_thirteen;
            $return_data[$counter] = $value;
            $counter++;
          }
        }else if($type == "16"){
          if(!in_array($value->level_sixteen, $dupe)){
            $dupe[$counter] = $value->level_sixteen;
            $return_data[$counter] = $value;
            $counter++;
          }
        }else if($type == "20"){
          if(!in_array($value->level_twenty, $dupe)){
            $dupe[$counter] = $value->level_twenty;
            $return_data[$counter] = $value;
            $counter++;
          }
        }
        if($counter == 5){
          break;
        }
      }
    }else{
      $counter = 0;

      foreach($builds as $key => $value){
        $return_data[$counter] = $value;
        $counter++;
      }
    }
    //print_r($return_data);
    //echo "<br>";

    return $return_data;
  }

  private function getBuildsWinChance($builds){
    foreach($builds as $key => $value){
      $sub_query = \App\GlobalHeroTalents::Filters($this->hero, $this->game_versions, $this->game_type, $this->player_league_tier,
                                            $this->hero_league_tier, $this->role_league_tier, $this->game_map, $this->hero_level, $this->mirror, $this->region)
                     ->select('win_loss', DB::raw('SUM(games_played) as games_played'))
                     ->where('level_one', $value->level_one)
                     ->where('level_four', $value->level_four)
                     ->where('level_seven', $value->level_seven)
                     ->where('level_ten', $value->level_ten)
                     ->groupBy('win_loss');

     $build_data = \App\GlobalHeroTalents::select(
         DB::raw('SUM(IF(win_loss = 1, games_played, 0)) AS wins'),
         DB::raw('SUM(IF(win_loss = 0, games_played, 0)) AS losses')
       )
       ->from(DB::raw('(' . $sub_query->toSql() . ') AS data'))
       ->mergeBindings($sub_query->getQuery())
       ->get();



       $builds[$key]->wins = $build_data[0]->wins;
       $builds[$key]->losses = $build_data[0]->losses;
       $builds[$key]->games_played = $build_data[0]->wins + $build_data[0]->losses;

       if($builds[$key]->games_played > 0){
         $builds[$key]->win_rate = $build_data[0]->wins / ($build_data[0]->wins + $build_data[0]->losses);
       }
    }

    return $builds;
  }

  public function getGlobalHeroTalentData($type){
    $builds = $this->getTopFiveBuilds($type);
    $builds = $this->getBuildsWinChance($builds);
    return $builds;
  }
}
