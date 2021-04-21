<?php

namespace App\Http\Controllers\Api\Parser;

use App\Services\Parsers\OnlinerParser;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Jobs\CatalogParsingJob;
use App\Jobs\CatalogItemParsingJob;
use App\Jobs\ProductParamParsingJob;
use App\Models\Catalog;
use Illuminate\Support\Facades\DB;
// use Carbon\Carbon;

class CatalogController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return response()->json(Catalog::get(), 200);
        // return 'Вывод всех товарных групп';
        //https://artcrmvds.h1n.ru/api/getCatalogParts
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }

    # Получить всю структуру каталога
    public function startCatalogParsing()
    {
        dispatch(new CatalogParsingJob);
        return response()->json(['status'=>'started', 'message'=>'Event запущен'], 200);
    }

    # начать получение списка моделей
    public function startCatalogItem($productType)
    {
        $urlToParse = Catalog::where('name', $productType)->first();

        dispatch(new CatalogItemParsingJob([
            'name'=>$productType,
            'part'=>1,
            'url'=>$urlToParse['url']
        ]));
        return 'parse catalog : '.$productType;
    }
    # начать парсинг
    public function startProductParamParsing($productType) 
    {
        $productBase = Catalog::where('name', $productType)->first();
        // dd($productBase);
        
        dispatch(new ProductParamParsingJob([
            // $productBase
            'name'=>$productType,
            'start'=>now(),
            'part'=>1,
            'getParams'=>true,
            'repeat'=>true,
            'item'=>false,
            'target'=>null
        ]));
        return 'startProductParamParsing';
    }

    public function startProductParamItem($productType, $productId) {

        $productBase = Catalog::where('name', $productType)->first();

        // dispatch(new ProductParamParsingJob([
        //     // $productBase
        //     'name'=>$productType,
        //     'part'=>1,
        //     'getParams'=>false,
        //     'repeat'=>false,
        //     'item'=>true,
        //     'target'=>$productId
        // ]));



        return OnlinerParser::getProductParams(
            [
                'name'=>$productType,
                'part'=>1,
                'getParams'=>false,
                'repeat'=>false,
                'item'=>true,
                'target'=>$productId
            ]
        );
    }

    public function getProductDescriptions($productType, $part=0) {
        $products = DB::table($productType)
            ->select('id', 'name', 'params', 'images', 'html_url', 'onliner_id', 'brend', 'price_min')
            ->where('params', '!=', null)
            ->offset($part*1000)
            ->limit(1000)
            ->get();

        $count = DB::table($productType)->where('params', '!=', null)->count();

        return response()->json([
            'products' => $products,
            'productCount' => $count,
            'count' => ceil( $count / 1000 ),
            'part' => $part+1
        ], 200);
    }

    public function getProductPrices($productType) {
        $startTime = now();
        $products = DB::table($productType)
            ->select('id', 'onliner_key')
            // ->where('params', '!=', null)
            ->limit(1)
            ->get();

            // dd($products);

            $resp = ( OnlinerParser::getProductPrices(
                    [
                        'table'=>$productType,
                        'onliner_key'=>$products[0]->onliner_key,
                        'product_id'=>$products[0]->id
                    ]
                )
            );
            return $resp;
    }
}
