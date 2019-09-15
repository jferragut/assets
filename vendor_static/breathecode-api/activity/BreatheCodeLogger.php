<?php

namespace BreatheCode;
use \Google\Cloud\Datastore\DatastoreClient;
use \Google\Cloud\Datastore\Query\Query;
use \BreatheCode\Activity\BCActivity;

class BreatheCodeLogger{

    private static $datastore = null;

    public static function datastore(){
        if(!self::$datastore) self::$datastore = new DatastoreClient([
            'projectId' => GOOGLE_PROJECT_ID,
            'keyFilePath' => '../../breathecode-47bde0820564.json'
        ]);

        return self::$datastore;
    }

    public static function setDatastore($datastore){
        if($datastore) self::$datastore = self::$datastore;
    }

    public static function validateActivityProperties($activity, $slug){
        $props = ['track_on_active_campaign','track_on_log'];
        foreach($props as $prop)
            if(!isset($activity[$prop])){
                throw new \Exception("Activity ".$slug." is missing property: ".$prop);
            }
        return true;
    }

    // public static function addMessagesToActivities($messages){
    //     foreach($messages as $slug => $msg){
    //         if(isset(self::$_activites[$slug])) throw new \Exception("Duplicated Activity Slug");
    //         self::validateActivityProperties($msg, $slug);
    //         self::$_activites[$slug] = [
    //             "track_on_active_campaign" => $msg["track_on_active_campaign"],
    //             "track_on_log" => $msg["track_on_log"]
    //         ];
    //     }
    // }

    static function logActivity($activity, $user=null){
        $student = (!$user) ? $activity["user"] : $user;

        if(empty($student)) throw new \Exception('Invalid or empty user for the activity');

        $instance = BCActivity::factory($activity["slug"]);
        if($instance->trackOnLog()){
            $encoded = $instance->encode($student, $activity);
            $record = self::datastore()->entity($instance->slug, $encoded);
            $result = self::datastore()->insert($record);
        }

        if($instance->trackOnActiveCampaign()){
            \AC\ACAPI::start(AC_API_KEY);
            \AC\ACAPI::setupEventTracking('25182870', AC_EVENT_KEY);
            if(!is_string($student)){
                if(!empty($student->email)) $student = $student->email;
                else if(!empty($student->username)) $student = $student->username;
                else throw new \Exception('Missing user email or username');
            }
            $data = (empty($data["data"])) ? 'No additional data' : $data["data"];
            \AC\ACAPI::trackEvent($student, $activity["slug"], $data);
        }

    }

    // public static function deleteActivity($student, $type=null){
    //     $record = self::datastore()->entity($type, $activity);
    //     self::datastore()->insert($record);
    // }

    public static function retrieveActivity($filters, $type='student_activity'){

        $query = self::datastore()->query()->kind($type);

        $instance = BCActivity::factory($type);
        $query = $instance->filter($query, $filters);
        if(!$query) throw new \Exception("Undefined query for ".$type);

        $items = self::datastore()->runQuery($query);
        $results = [];
        foreach($items as $ans) {
            $results[] = $query = $instance->decode($ans);
        }
        return $results;
    }

}