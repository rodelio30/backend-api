<?php

namespace App\Controllers;

class TestMongoController extends BaseResourceController
{
    public function index()
    {
        try {
    //            public array $mongodb = [
    //     'hostname'   => '10.10.197.3',
    //     'port'       => 27017,
    //     'username'   => 'livechat_messages',
    //     'password'   => 'Y845akkHeYzFC8y5',
    //     // 'password' => 'XASvWsPkDxnDKpBD',
    //     'database'   => 'livechat_messages',
    //     // 'authSource' => 'admin';
    //     'options'    => [
    //         'connectTimeoutMS' => 5000,
    //         'socketTimeoutMS'  => 10000
    //     ]
    // ];
            // $client = new \MongoDB\Client("mongodb://livechat_messages:Y845akkHeYzFC8y5@10.10.197.3:27017/");
            $client = new \MongoDB\Client("mongodb://root:XASvWsPkDxnDKpBD@103.205.208.104:27017/");
            // $client = new \MongoDB\Client("mongodb://username:password@localhost:27017/");

            $db = $client->selectDatabase('livechat_messages');
            // $db = $client->selectDatabase('your_database_name');

            $collection = $db->selectCollection('fwadmin_messages');
            // $collection = $db->selectCollection('your_collection');
            
            $count = $collection->countDocuments(); 

            return $this->response->setJSON([
                'status' => 'success',
                'message' => 'MongoDB connection successful! Driver is loaded and database is reachable.',
                'documents_in_collection' => $count
            ]);

        }
        //  catch (\Exception $e) {
        //     return $this->response->setJSON([
        //         'status' => 'error',
        //         'message' => $e->getMessage()
        //     ]);
        // }
        catch (\Exception $e) {

            // 👉 PUT THE LINE HERE
            return $this->response->setJSON([
                'status' => 'error',
                'message' => $e->getMessage(),   // <--- HERE
            ]);
        }
    }
}
