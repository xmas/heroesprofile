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

class GlobalHeroStatData
{
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

  public static function instance($timeframe, $game_type, $player_league_tier,
                               $hero_league_tier, $role_league_tier, $game_map, $hero_level,
                               $mirror, $region)
  {
    return new GlobalHeroStatData($timeframe, $game_type, $player_league_tier,
                                 $hero_league_tier, $role_league_tier, $game_map, $hero_level,
                                 $mirror, $region);
  }


  public function __construct($timeframe, $game_type, $player_league_tier,
                               $hero_league_tier, $role_league_tier, $game_map, $hero_level,
                               $mirror, $region) {
    $this->timeframe = $timeframe;
    $this->game_type = $game_type;
    $this->player_league_tier = $player_league_tier;
    $this->hero_league_tier = $hero_league_tier;
    $this->role_league_tier = $role_league_tier;
    $this->game_map = $game_map;
    $this->hero_level = $hero_level;
    $this->mirror = $mirror;
    $this->region = $region;
  }

  private function getHeroWinLosses(){
    $sub_query = \App\GlobalHeroStats::Filters($this->game_versions, $this->game_type, $this->player_league_tier,
                                          $this->hero_league_tier, $this->role_league_tier, $this->game_map, $this->hero_level, $this->mirror, $this->region)
                   ->select('hero', 'win_loss', DB::raw('SUM(games_played) as games_played'))
                   ->groupBy('hero', 'win_loss');

    $global_hero_data = \App\GlobalHeroStats::select(
        'hero',
        DB::raw('SUM(IF(win_loss = 1, games_played, 0)) AS wins'),
        DB::raw('SUM(IF(win_loss = 0, games_played, 0)) AS losses'),
        DB::raw('0 as games_banned')
      )
      ->from(DB::raw('(' . $sub_query->toSql() . ') AS data'))
      ->mergeBindings($sub_query->getQuery())
      ->groupBy('hero')
      ->get();

      /*
      print_r($sub_query->toSql());
      echo "<br>";
      print_r($sub_query->getBindings());
      echo "<br>";
      */
      return $global_hero_data;
  }

  private function getHeroBans(){
    $global_ban_data = \App\GlobalHeroBans::Filters($this->game_versions, $this->game_type, $this->player_league_tier,
                                          $this->hero_league_tier, $this->role_league_tier, $this->game_map, $this->hero_level, $this->region)
                      ->select('hero', DB::raw('SUM(bans) as games_banned'))
                      ->groupBy('hero')
                      ->get();
    return $global_ban_data;
  }

  private function getHeroChange(){
    if(count($this->timeframe) == 1
      && count($this->game_type) == 1
      && $this->game_type[0] != "br"
      && count($this->game_map) == 0
      && count($this->player_league_tier) == 0
      && count($this->hero_league_tier) == 0
      && count($this->role_league_tier) == 0
      && count($this->hero_level) == 0
      && count($this->region) == 0){
      $major_season_game_version = \App\SeasonGameVersions::where('game_version', '>=', '2.43')
                                  ->select(DB::raw("DISTINCT(SUBSTRING_INDEX(game_version, '.', 2)) as game_version"))
                                  ->orderBy('game_version', 'DESC')
                                  ->get();
      $major_season_game_version = json_decode(json_encode($major_season_game_version),true);
      $found = 0;
      $timeframe = "";
      if(count($major_season_game_version) > 0){
        for($i = 0; $i < count($major_season_game_version); $i++){
          if($found){
            $timeframe = $major_season_game_version[$i]["game_version"];
            break;
          }
          if($major_season_game_version[$i]["game_version"] == $this->timeframe[0]){
            $found = 1;
          }
        }
      }

      if(!$found){
        foreach (Session::get("all_minor_patch") as $key => $value){
          if($found){
            $timeframe = $value;
            break;
          }
          if($value == $this->timeframe[0]){
            $found = 1;
          }
        }
      }


      $change_data = \App\GlobalHeroChange::where('game_version', $timeframe)
                        ->where('game_type', $this->game_type[0])
                        ->select('hero', 'win_rate')
                        ->get();
      return $change_data;
    }
  }

  private function combineData(){
    $game_version_counter = 0;
    for($i = 0; $i < count($this->timeframe); $i++){
      if(in_array($this->timeframe[$i], Session::get("all_major_patch"))){
        for($j = 0; $j < count(Session::get("major_to_minor_patch_mapping")[$this->timeframe[$i]]); $j++){
          $this->game_versions[$game_version_counter] = Session::get("major_to_minor_patch_mapping")[$this->timeframe[$i]][$j];
          $game_version_counter++;
        }

      }else{
        $this->game_versions[$game_version_counter] = $this->timeframe[$i];
        $game_version_counter++;
      }
    }

    $global_hero_data = $this->getHeroWinLosses();
    $global_ban_data = $this->getHeroBans();
    $global_change_data = $this->getHeroChange();

    $total_games = ($global_hero_data->sum('wins') + $global_hero_data->sum('losses')) / 10;
    $total_bans = $global_ban_data->sum('games_banned');

    for($i = 0; $i < count($global_hero_data); $i++){
      $global_hero_data[$i]->games_played = $global_hero_data[$i]->wins + $global_hero_data[$i]->losses;
      if($global_hero_data[$i]->games_played){
        $global_hero_data[$i]->win_rate = $global_hero_data[$i]->wins / ($global_hero_data[$i]->wins + $global_hero_data[$i]->losses);
        $global_hero_data[$i]->pick_rate = $global_hero_data[$i]->games_played / $total_games;
      }else{
        $global_hero_data[$i]->win_rate = 0;
        $global_hero_data[$i]->pick_rate = 0;
      }

      foreach ($global_ban_data as $ban_data) {
        if($ban_data->hero == $global_hero_data[$i]->hero){
          $global_hero_data[$i]->games_banned = $ban_data->games_banned;
          break;
        }
      }

      if(count($global_change_data) > 0){
        foreach ($global_change_data as $change_data) {
          if($change_data->hero == $global_hero_data[$i]->hero){
            $global_hero_data[$i]->change = ($global_hero_data[$i]->win_rate * 100)  - $change_data->win_rate;
            break;
          }

        }
      }

      if($global_hero_data[$i]->games_banned > 0){
        $global_hero_data[$i]->popularity = ($global_hero_data[$i]->games_played / $total_games) * 100;
      }else{
        $global_hero_data[$i]->popularity = (($global_hero_data[$i]->games_banned + $global_hero_data[$i]->games_played) / $total_games) * 100;
      }
      $global_hero_data[$i]->influence = round(($global_hero_data[$i]->win_rate - .5) * ($global_hero_data[$i]->pick_rate * 10000));
    }

    return $global_hero_data;
  }
  public function getGlobalHeroStatData(){
    return $this->combineData();
  }
}
