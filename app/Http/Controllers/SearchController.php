<?php

namespace App\Http\Controllers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

use Illuminate\Http\Request;

class SearchController extends Controller
{
    private $page;
    private $url = [];
    private $filter = [];
    private $data = [];
    private $setFilter = 1;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->page = 1;
        $this->url = (Object) [
            'tokopedia' => 'http://ajax.tokopedia.com',
            'bukalapak' => 'http://api.bukalapak.com'
        ];

        $this->filter = [
          [
            'tokopedia' => 23,
            'bukalapak' => 'Relevansi'
          ],
          [
            'tokopedia' => 3,
            'bukalapak' => 'Termurah'
          ],
          [
            'tokopedia' => 4,
            'bukalapak' => 'Termahal'
          ]
        ];
    }

    private function search($query) {
        $tp = $this->searchTP($query);
        if(count($tp) > 0)
            foreach ($tp as $d) {
                $this->data[] = [
                  'id' => $d->product_id,
                  'name' => $d->product_name,
                  'price' => (int) filter_var($d->product_price, FILTER_SANITIZE_NUMBER_INT),
                  'image' => $d->product_image_full,
                  'url' => $d->product_url,
                  'source' => 'tokopedia',
                  'location' => $d->shop_location
                ];
            }
        $bl = $this->searchBL($query);
        if(count($bl) > 0)
            foreach ($bl as $d) {
                $this->data[] = [
                  'id' => $d->id,
                  'name' => $d->name, //.substr(1,d.name.length-1),
                  'price' => $d->price,
                  'image' => $d->images ? $d->images[0] : 0,
                  'url' => $d->url,
                  'source' => 'bukalapak',
                  'location' => $d->city . ', ' . $d->province
                ];
            }

        if(count($this->data) > 0) {
            usort($this->data, function($a, $b) {
                return $a['price'] - $b['price'];
            });
            if($this->setFilter = 2) {
              $this->data = array_reverse($this->data);
            }
        }

        return $this->data;
    }

    private function searchBL($keyword)
    {
        $client = new Client(['base_uri' => $this->url->bukalapak]);
        try {
            $res = $client->request('GET', '/v2/products.json', [
                'query' => [
                    'keywords' => $keyword,
                    'page' => $this->page,
                    'per_page' => 6,
                    'sort_by' => $this->filter[$this->setFilter]['bukalapak']
                ]
            ]);
            if($res->getStatusCode() == '200') {
                return json_decode((String) $res->getBody())->products;
            } else {
                return $this->setError();
            }
        } catch (RequestException $e) {
            //return $e->getRequest();
            if ($e->hasResponse()) {
                //return $e->getResponse();
            }
            return [];
        }
    }

    private function searchTP($keyword)
    {
        $client = new Client(['base_uri' => $this->url->tokopedia]);
        try {
            $res = $client->request('GET', '/search/v2/product', [
                'query' => [
                    'q' => $keyword,
                    'start' => ($this->page - 1) * 6,
                    'rows' => 6,
                    'ob' => $this->filter[$this->setFilter]['tokopedia'],
                    'device' => 'android'
                ]
            ]);
            if($res->getStatusCode() == '200') {
                return json_decode((String) $res->getBody())->data->products;
            } else {
                return $this->setError();
            }
        } catch (RequestException $e) {
            //return $e->getRequest();
            if ($e->hasResponse()) {
                //return $e->getResponse();
            }
            return [];
        }
    }

    public function getIndex(Request $req)
    {
        $this->page = $req->has('page') ? $req->input('page') : $this->page;
        $this->setFilter = $req->has('filter') ? $req->input('filter') : 1;
        if($this->setFilter < 0 || $this->setFilter > 2) {
          $this->setFilter = 1;
        }
        if($req->has('keyword')) {
            return response()->json([
                'code' => 200,
                'filter' => (int) $this->setFilter,
                'page' => $this->page,
                'data' => $this->search($req->input("keyword"))
            ]);
        } else {
            return response()->json([
                'code' => 404,
                'message' => 'keyword param missing'
            ]);
        }
    }

    private function setError() {
        return [];
    }
}
