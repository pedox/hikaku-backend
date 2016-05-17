<?php

namespace App\Http\Controllers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

use Illuminate\Http\Request;

class ProductController extends Controller
{
    private $url;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->page = 1;
        $this->url = (Object)[
            'tokopedia' => 'https://ws.tokopedia.com/',
            'bukalapak' => 'https://api.bukalapak.com/'
        ];
    }

    public function getTokopedia($id)
    {
        $client = new Client(['base_uri' => $this->url->tokopedia]);
        try {
            $res = $client->request('POST', '/v4/product/get_detail.pl', [
                'headers' => [
                    'Content-MD5' => 'a024da28a1cd5f50363933d8e009695a',
                    'Date' => 'Mon, 11 Apr 2016 16:16:50 +0700',
                    'X-Method' => 'POST',
                    'Authorization' => 'TKPD Tokopedia:RO9P4JD5Tk9na4S7kJGqRoZdNC8='
                ],
                'form_params' => [
                    'product_id' => $id,
                ]
            ]);
            if ($res->getStatusCode() == '200') {
                $product = json_decode((String)$res->getBody())->data;
                if (!$product) return false;

                $images = [];
                foreach ($product->product_images as $d) {
                    $images[] = $d->image_src_300;
                }

                return [
                    'name' => $product->info->product_name,
                    'description' => $product->info->product_description,
                    'price' => (int)filter_var($product->info->product_price, FILTER_SANITIZE_NUMBER_INT),
                    'url' => $product->info->product_url,
                    'images' => $images
                ];
            } else {
                return false;
            }
        } catch (RequestException $e) {
            return false;
        }
    }

    public function getBukalapak($id)
    {
        $client = new Client(['base_uri' => $this->url->bukalapak]);
        try {
            $res = $client->request('GET', "v2/products/" . $id . ".json");
            if ($res->getStatusCode() == '200') {
                $product = json_decode((String)$res->getBody())->product;
                if (!$product) return false;
                return [
                    'name' => $product->name,
                    'description' => $product->desc,
                    'price' => $product->price,
                    'url' => $product->url,
                    'images' => $product->images
                ];
            } else {
                return false;
            }
        } catch (RequestException $e) {
            return false;
        }
    }

    public function getProduct(Request $req, $product)
    {
        $product_data = null;
        if ($req->has('id')) {
            if ($product == "bukalapak") {
                $product_data = $this->getBukalapak($req->input('id'));
            } else
                if ($product == "tokopedia") {
                    $product_data = $this->getTokopedia($req->input('id'));
                } else {
                    return $this->setError();
                }
        } else {
            return $this->setError();
        }

        if (!$product_data) return $this->setError();

        return response()->json([
            'code' => 200,
            'id' => $req->input('id'),
            'product' => $product,
            'data' => $product_data
        ]);
    }

    private function setError()
    {
        return response()->json([
            'code' => 404,
            'message' => 'Product not found'
        ]);
    }
}
