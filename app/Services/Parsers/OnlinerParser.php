<?php

namespace App\Services\Parsers;

use App\Models\Catalog;
use DiDom\Document; //
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Jobs\CatalogItemParsingJob;
use App\Jobs\ProductParamParsingJob;
use Exception;

// use Sunra\PhpSimple\HtmlDomParser;
// use Symfony\Component\DomCrawler\Crawler;
// use Goutte\Client;

// use \Libraries\simplehtmldom\simple_html_dom;

// 
// use simplehtmldom\HtmlWeb;
// use simplehtmldom\simple_html_dom;

class OnlinerParser {

    public static function getCatalog() {
        $catalog = Catalog::get();
        $catalogByName = array();
        foreach ($catalog as $key => $value) {
            $catalogByName[$value['name']] = $value;
        }

        $base = 'https://catalog.onliner.by/';

        $document = new Document($base, true);
        $NamesById = [];

        foreach ($document->find('.catalog-navigation-classifier__item') as $key => $name) {
            $dataId = $name->getAttribute('data-id');
            if ( count(explode('brand-', $dataId)) == 1 ) {
                $NamesById[$name->getAttribute('data-id')] = $name->text();
            }
        }

        $blocks = $document->find('.catalog-navigation-list__category');

        foreach($blocks as $key => $block) {
            $blockNum = $block->getAttribute('data-id');
            if ( isset($NamesById[$blockNum]) ) {
                # Создаем блок
                if ( !isset( $catalogByName[trim($NamesById[$blockNum])] ) ) {
                    $blockItem = Catalog::create(
                        [
                            'name'=>trim($NamesById[$blockNum]),
                            'parent_id'=>'0',
                            'label'=>trim($NamesById[$blockNum]),
                            'type'=>'block'
                        ]
                    );
                    $blockId = $blockItem->id;
                } else {
                    $blockId = $catalogByName[ trim($NamesById[$blockNum]) ]['id'];
                }


                $items = $block->find('.catalog-navigation-list__aside-title');
                $groups = $block->find('.catalog-navigation-list__dropdown');
                foreach ($items as $key => $item) {
                    $links = $groups[$key]->find('.catalog-navigation-list__dropdown-item');
                    if ( !isset( $catalogByName[ trim( $item->text() ) ] ) ) {
                        $subBlockItem = Catalog::create(
                            [
                                'name'=>trim($item->text()),
                                'parent_id'=>$blockId,
                                'label'=>trim($key),
                                'type'=>'subBlock',
                            ]
                        );
                        $subBlockItemId = $subBlockItem->id;
                    } else {
                        $subBlockItemId = $catalogByName[ trim($item->text()) ]['id'];
                    }

                    foreach ($links as $key => $link) {
                        $linkName = $link->find('.catalog-navigation-list__dropdown-title')[0]->text();
                        $name = explode('?', explode( '/', $link->getAttribute('href') )[3] )[0];
                        if (!isset($catalogByName[$name])) {
                            $item = Catalog::create(
                                [
                                    'name'=>$name,
                                    'parent_id'=> $subBlockItemId,
                                    'label'=>trim($linkName),
                                    'labels'=>trim($linkName),
                                    'url'=>explode('?', $link->getAttribute('href'))[0],
                                    'full_url'=>$link->getAttribute('href'),
                                    'type'=>'item'
                                ]
                            );
                        }
                    }
                }
            }
        }


    }

    public static function getCatalogItem($data) {
        $catalogItem = $data['name'];
        # Проверяем таблицу на существование:
        if (!Schema::hasTable($catalogItem)) {
            self::createCatalogItemTable($catalogItem);
        }

        $baseUrl = 'https://catalog.onliner.by/sdapi/catalog.api/search/'.$catalogItem.'?page='.$data['part'];

        try {
            $pageJSON = file_get_contents($baseUrl);
        } catch (Exception $e) { 
            echo 'Exception';
            DB::table('jobs')->delete();
            sleep(20);
            dispatch(new CatalogItemParsingJob($data));
            die($e);
        }

        

        $pageObject = json_decode($pageJSON, true);

        $productsToInsert = array();
        $productsToUpdate = array();
        $onlinerIds = array();

        foreach ($pageObject['products'] as $index => $item) {
            $onlinerIds[] = $item['id'];

            $productItem = [
                'name' => $item['name'],
                'onliner_id' => $item['id'],
                'onliner_key' => $item['key'],
                'full_name' => $item['full_name'],
                'name_prefix' => $item['name_prefix'],
                'extended_name' => $item['extended_name'],
                'image_header_url' => $item['images']['header'],
                'descriptions' => $item['description'],
                'price_min' => $item['prices']!==null ? floatval($item['prices']['price_min']['amount']) : 00.00,
                'popularity' => ((int)$data['part']-1) * 30 + (int)$index + 1,
                'html_url' => $item['html_url'],
                'brend'=> explode(' '.$item['name'], $item['full_name'])[0]
            ];
            
            $productsToInsert[] = $productItem;
        }

        # Получаем существующие товары:
        $oldProducts = DB::table($catalogItem)
                    ->whereIn('onliner_id', $onlinerIds)
                    ->get();

        # Добавляем productsToUpdate удаляем productsToInsert
        foreach ($oldProducts as $keyO => $oldProduct) {
            foreach ($productsToInsert as $keyP => $product) {
                if ($product['onliner_id'] == $oldProduct->onliner_id) {
                    $itemToUpdate = [
                        'id'=>$oldProduct->id
                    ];
                    $productsToUpdate[] = $itemToUpdate;
                    unset($productsToInsert[$keyP]);
                }
            }
        }

        # Добавляем в базу:
        if (count($productsToInsert) > 0) {
            DB::table($catalogItem)->insert($productsToInsert);
        } else {
            // foreach ($productsToUpdate as $key => $value) {
                // DB::table('users')
                //     ->where('id', $value['id'])
                //     ->update(['updated_at'=>now()]);
            // }
        }
        


        if ($pageObject['page']['last'] === $data['part']) {
            echo 'end';
            # получаем количество товаров в базе:
            $total_count = DB::table($catalogItem)->count();
            # меняем is_active, total_count раздела
            DB::table('Catalog')
                ->where('name', $catalogItem)
                ->update(array(
                    'is_active' => 1,
                    'total_count' => $total_count
                ));

            $data['total_count'] = $total_count;

            # Добавить эвент для web socket
            // $dataToEvent = [
            //     'topic_id'=>$data['name'],
            //     'data'=>$data
            // ];
            // \App\Classes\Socket\Pusher::sendDataToServer($dataToEvent);

        } else {
            $data['part']++;
            // usleep(100);
            dispatch(new CatalogItemParsingJob($data));
            echo (int)memory_get_peak_usage()/1000000 . ' = '.$data['part'];
        }
    }

    public static function getProductParams($data) {
        if ($data['getParams']) {
            // echo 'получаем параметры';
            // $baseUrl = 'https://catalog.onliner.by/sdapi/catalog.api/facets/'.$data['name'];
            // $newParamsData = json_decode( file_get_contents($baseUrl) , true);
            // # обновить данные JSON в ДБ
            // DB::table('Catalog')
            //     ->where('name', $data['name'])
            //     ->update(['params' => $newParamsData]);

            // // dd($newParamsData['facets']);

            // foreach ($newParamsData['facets'] as $key1 => $blocks) {
            //     // echo '
            //     //     -'.$key1;
            //     foreach ($blocks['items'] as $key2 => $block) {
            //         // echo '
            //         //     -'.$block['name'];
            //         $paramsData[$block['description']] = $block;
            //         $paramsData[$block['name']] = $block;
            //     }
            // }
        }
        // dd($paramsData);

        # Получаем список товаров для парсинга:
        if (!$data['item']) {
            $products = DB::table($data['name'])
            // ->whereNull('pars_date')
            ->where('pars_date', '<', $data['start'])
            ->orWhereNull('pars_date')
            ->limit(1)
            ->get();
            if (count($products) == 0) {
                # Отравляем WebSocket:
                ############################################################################################################
                // $dataToEvent = [
                //     // 'topic_id'=>'onNewData',
                //     'topic_id'=>$data['name'].'Desc',
                //     'data'=>'end'
                // ];
                // \App\Classes\Socket\Pusher::sendDataToServer($dataToEvent);
                ############################################################################################################
                # Сохраняем нужное количество товаров в Catalog:
                DB::table('Catalog')
                    ->where('name', $data['name'])
                    ->update([ 'descriptions_count' => $data['part'] ]);
                ############################################################################################################
                die('Все товары для '.$data['name'].' спаршены дата: '.$data['start']);
            }
        } else {
            $products = DB::table($data['name'])
            ->where('id', $data['target'])
            ->limit(1)
            ->get();
            // dd($products);
            if (count($products) == 0) {
                die('Данный товар не существует');
            }
        }
        
        

        $document = new Document($products[0]->html_url, true);
        $paramsBlock = $document->find('.product-specs__table');

        // echo count($paramsBlock);

        $tables = $paramsBlock[0]->find('tbody');
        // echo count($tables);
        $params = [];

        foreach ($tables as $key => $table) {
            # получаем заголовок параметра:
            $titleParam = trim( $table->find('.product-specs__table-title-inner')[0]->text() );
            // echo '
            // ---('.$titleParam.')';
            foreach ($table->find('tr') as $key1 => $value) {
                if ($key1 != 0) {

                    $paramBlocks = $value->find('td');
                    $paramBlock = $paramBlocks[0];
                    if (!isset($paramBlocks[1])) {
                        continue;
                    }
                    $valueBlock = $paramBlocks[1];
                        $check = $paramBlock->find('.product-tip__term');
                        if ( count($check) > 0 ) {
                            // echo '
                            // ('.count($check).')'.$titleParam.' # '.trim( $check[0]->text() ).' = '.trim($valueBlock->text());
                            $dataToParam = trim($valueBlock->text());
                            if ($dataToParam != '') {
                                $params[$titleParam][trim( $check[0]->text() )]['value'] = trim($valueBlock->text());
                                $params[$titleParam][trim( $check[0]->text() )]['bool'] = true;
                            } 
                            else {
                                if ( count( $valueBlock->find('.i-tip') ) ) {
                                    $params[$titleParam][trim( $check[0]->text() )]['bool'] = true;
                                } else {
                                    $params[$titleParam][trim( $check[0]->text() )]['bool'] = false;
                                }
                                $params[$titleParam][trim( $check[0]->text() )]['value'] = null;
                            }
                            

                        } else {
                            // echo '
                            // !!!!!!!'.count($check).'!!!!!!!!'.$titleParam.' # '.trim( $paramBlock->text() ).' = '.trim($valueBlock->text());
                            $dataToParam = trim($valueBlock->text());
                            if ($dataToParam != '') {
                                $params[$titleParam][trim( $paramBlock->text() )]['value'] = trim($valueBlock->text());
                                $params[$titleParam][trim( $paramBlock->text() )]['bool'] = true;
                            } else {
                                if ( count( $valueBlock->find('.i-tip') ) ) {
                                    $params[$titleParam][trim( $paramBlock->text() )]['bool'] = true;
                                } else {
                                    $params[$titleParam][trim( $paramBlock->text() )]['bool'] = false;
                                }
                                $params[$titleParam][trim( $paramBlock->text() )]['value'] = null;
                            }

                        }
                        // echo '('.$key1.')';
                    }
               
            }
        }

        // dd($params);

        # Получаем фото:
        $imgToUpdate = array();
        $imagesSmall = $document->find('.product-gallery__thumb-img');
        foreach ($imagesSmall as $key => $image) {
            $imgToUpdate[] = $image->getAttribute('src');
        }
        $imagesBig = $document->find('.product-gallery__thumb');
        foreach ($imagesBig as $key => $image) {
            $imgToUpdate[] = $image->getAttribute('data-original');
        }
        foreach ($imgToUpdate as $key => $value) {
            if ($value == null) {
                unset($imgToUpdate[$key]);
            }
        }

        # Сохраняем JSON
        DB::table($data['name'])
            ->where('id', $products[0]->id)
            ->update(['params'=>$params, 'pars_date'=>now(), 'images'=>$imgToUpdate]);

        # Распаршиваем HTML:

        if ($data['repeat']) {
            dispatch(new ProductParamParsingJob([
                'name'=>$data['name'],
                'start'=>$data['start'],
                'part'=>(int)$data['part']+1,
                'getParams'=>true,
                'repeat'=>true,
                'item'=>false,
                'target'=>null
            ]));
            ################################################################################################
            # обновляем количество спаршенных товаров:
            // $dataToEvent = [
            //     // 'topic_id'=>'onNewData',
            //     'topic_id'=>$data['name'].'Desc',
            //     'data'=>$data['part']
            // ];
            // \App\Classes\Socket\Pusher::sendDataToServer($dataToEvent);

            ####################################################################

            echo $products[0]->id.' для '.$data['name'].'=>'.$data['start'];
        } else {
            $params['images'] = json_decode( str_replace('\\', '', json_encode($imgToUpdate)) , true);
            return json_encode($params) ;
        }
        
        
    }

    public static function getProductPrices($data) {
        // $startTime = now();
        $table = $data['table'];
        $onliner_key = $data['onliner_key'];
        $product_id = $data['product_id'];
        // $baseUrl = 'https://catalog.onliner.by/sdapi/shop.api/products/'.$onliner_key.'/positions';
        $baseUrl = 'https://shop.api.onliner.by/products/'.$onliner_key.'/positions';
        $pageJSON = file_get_contents($baseUrl);
        $pageObject = json_decode($pageJSON, true);
        // $pageObject['start'] = $startTime;
        // $pageObject['stop'] = now();
        return json_encode($pageObject);
    }

    # Получение параметров для обработки: +++++++++++
    # Request URL: https://catalog.onliner.by/sdapi/catalog.api/facets/hob_cooker

    # Получение цены и продавцов товара
    # Request URL:  https://catalog.onliner.by/sdapi/shop.api/products/slsgii60wh/positions
    #               https://shop.api.onliner.by/products/slsgii60wh/positions

    # блочит:
    # https://catalog.onliner.by/sdapi/catalog.api/search/faucet?page=354

    # https://shop.api.onliner.by/products/slsgii60wh/positions
    # https://shop.api.onliner.by/search/faucet?page=354

    # https://catalog.onliner.by/sdapi/catalog.api/facets/oven_cooker - Получить описание параметров
    # https://catalog.onliner.by/sdapi/catalog.api/search/oven_cooker?mfr[0]=maunfeld



    private static function createCatalogItemTable(string $catalogItem) {
        Schema::create($catalogItem, function($table) {
            $table->increments('id');
            $table->integer('onliner_id');
            $table->integer('popularity');
            $table->timestamps();
            $table->string('onliner_key');
            $table->string('name');
            $table->string('brend')->nullable();
            $table->string('full_name');
            $table->string('name_prefix');
            $table->string('extended_name');
            $table->mediumText('image_header_url')->nullable();
            $table->mediumText('descriptions');
            $table->mediumText('html_url');
            $table->decimal('price_min', 8, 2);
            $table->dateTime('pars_date')->nullable();
            // $table->json('params')->nullable();
            // $table->json('images')->nullable();
            
            $table->json('params')->nullable();
            $table->longText('images')->nullable();

            // $table->mediumText('params')->nullable();
        });
    }
}



