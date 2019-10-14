<?php

require_once 'db.php';
require_once ('mws_config.php');

//$csvFile = file('/uat/mt/MWSProducts/melon-test.csv');
/*  foreach($csvFile as $sCsvLine) {
 38     $aCsvLine = explode(',', $sCsvLine);
 39     if(isset($_GET['tier']) and $_GET['tier'] <> $aCsvLine[2] ) continue;
 40     if (preg_match_all('/B0\w{8}/',$sCsvLine,$m)) {
 41         foreach($m[0] as $asin) {
 42             $asin_array[] = $asin;
 43         }
 44     }
 45   }
 46 */

 //echo '<pre>$file='; var_dump($csvFile); echo "</pre>";

function invokeGetMatchingProduct(MarketplaceWebServiceProducts_Interface $service, $request, $region){

    try {
    header("Content-type: text/xml");
      //
	//header('Content-Type: application/xml; charset=utf-8');
	//echo '<pre>$reuest='; print_r($request); echo "</pre>";
    //echo '<pre>$service='; print_r($service); echo "</pre>";
    //echo "<pre> region=$region </pre>";
    $response = $service->GetMatchingProduct($request);
    $dom = new DOMDocument();
    $dom->loadXML($response->toXML());
	
	//$XML = str_replace('&', '&amp;', $XML);
	//$XML = str_replace('<', '&lt;', $XML);
	//echo '<pre>' . $XML . '</pre>';
    echo $dom->saveXML();
    return 0;

    $product_data = array();
    $csv_db_data = array();
    $category_data = array();
    $products = $dom->getElementsByTagName("Product");
    foreach ($products as $product) {
	  //echo "<pre>";var_dump($product);echo "</pre>";
      //echo "<pre>";var_dump($product->attributes);echo "</pre>";
	  //echo "<pre>";var_dump($product->ownerDocument);echo "</pre>";	
      // Fetching Seller SKU for product
      $sku_info = $product->getElementsByTagName("ASIN");

      $sku = $sku_info[0]->nodeValue;
      $product_data['asin'] = $sku;
      
      //Fetching Sales Ranking
      $category =  $product->getElementsByTagName("ProductCategoryId");
      $rank = $product->getElementsByTagName("Rank");
      $rank_array = array();
      $category_array = array();
      for($i = 0; $i < $category->length; $i++){
      	$rank_array['rank'.$i] = $rank[$i]->nodeValue;
      	$category_array['category'.$i] = $category[$i]->nodeValue;
      }
    
      $Price = $product->getElementsByTagName("AttributeSets");
      foreach ($Price as $key => $value) {
          // Fetching List Price of product
        $ListPrice = $value->getElementsByTagName("ListPrice"); //the price was not right -John 2019-09-06
        // $ListPrice = $value->getElementsByTagName("ListingPrice");
        foreach ($ListPrice as $k => $val) {
            // Fetching list price of product
      	  $amount = $val->getElementsByTagName("Amount");
          $product_data['price'] = $amount[0]->nodeValue;
            // Currency Code of list price of product
          $currencyCode = $val->getElementsByTagName("CurrencyCode");
          $product_data['currency'] = ($currencyCode[0]->nodeValue == "USD") ? 1 : 0;
        	$csv_array_datas = $GLOBALS['csv_array'];
        	foreach($csv_array_datas as $csv_array_data){
        	  $sku_comp_data = strpos($csv_array_data[4],$product_data['asin']);
        	  $url_comp_data = strpos($csv_array_data[0],$product_data['asin']);
        	  if($sku_comp_data > -1 || $url_comp_data > -1 ){
        	    $product_data['tier'] = $csv_array_data[2];
        	  	$product_data['owner'] = $csv_array_data[3];
                $product_data['csv_id'] = 0;
                break;
        	  }
        	}
          $select_sql = "SELECT * FROM csv WHERE product LIKE '%".$product_data['asin']."%' ORDER BY id LIMIT 1";
          $mysqli = mws_mysqlConnect();
          $result = $mysqli->query($select_sql);
          $rows = $result->fetch_assoc();
          if ($rows != NULL) {
              $product_data['csv_id'] = $rows['id'];
              $product_data['tier'] = $rows['tier'];
              $owner = $rows['owner'];
          }
          $select_owner_sql = $mysqli->query("SELECT ID FROM mws_owner WHERE Name = '".$owner."'");
    	  foreach($select_owner_sql as $select_owner){
    		  $product_data['owner'] = $select_owner['ID'];
    	  }
          if($rows['group_asin'] == $product_data['asin']){
            $product_data['type'] = 1;
          }else{
            $product_data['type'] = 2;
          }
            //Fetching list model of product
          $model = $value->getElementsByTagName("Model");	 
          $sku = $model[0]->nodeValue;
          
          $select_sku_sql = $mysqli->query("SELECT ID FROM mws_sku WHERE Sku = '".$sku."'");
          if($select_sku_sql->num_rows == 0){
		      $insert_sku_sql = "INSERT INTO mws_sku (Sku) VALUES ('".$sku."')";
			  echo "<pre> insert_sku_sql=$insert_sku_sql</pre>";
              //$mysqli->query($insert_sku_sql);
              $sku_id = $mysqli->insert_id;
              $product_data['sku'] = $sku_id;
          }else{
        	  foreach($select_sku_sql as $select_sku){
        		  $product_data['sku'] = $select_sku['ID'];
        	  }
          }
          
          $category1 = $category_array['category0'];
          $select_category1_sql = $mysqli->query("SELECT ID FROM mws_category1 WHERE Category = '".$category1."'");
          if($select_category1_sql->num_rows == 0){
		      $insert_category1_sql = "INSERT INTO mws_category1 (Category) VALUES ('".$category1."')";
              echo "<pre> insert_category1_sql=$insert_category1_sql</pre>";
			  //$mysqli->query($insert_category1_sql);
              $category1_id = $mysqli->insert_id;
              $product_data['category1'] = $category1_id;
          }else{
        	  foreach($select_category1_sql as $select_cat1){
        		  $product_data['category1'] = $select_cat1['ID'];
        	  }
          }
          
          $product_data['rank1'] =  $rank_array['rank0'];
          $category2 = $category_array['category1'];
          $select_category2_sql = $mysqli->query("SELECT * FROM mws_category2 WHERE Category = '".$category2."'");
          if($select_category2_sql->num_rows == 0){
		      $insert_category2_sql = "INSERT INTO mws_category2 (Category) VALUES ('".$category2."')";
			echo "<pre> insert_category2_sql=$insert_category2_sql</pre>";
	        //  $mysqli->query($insert_category2_sql);
	          $category2_id = $mysqli->insert_id;
	          $product_data['category2'] = $category2_id;
          }else{
        	  foreach($select_category2_sql as $select_cat2){
        		  $product_data['category2'] = $select_cat2['ID'];
        	  }
          }
          $product_data['rank2'] =  $rank_array['rank1'];
          $values = implode("','", $product_data);
          $insert_sql = "INSERT INTO mws_".$region." (asin,price,currency,tier,owner,csv_id,type,sku,category1,rank1,category2,rank2) VALUES ('".$values."')";
          echo "<pre> insert_sql=$insert_sql</pre>";
          //$mysqli->query($insert_sql);
       	}
      }
    }
    $_SESSION['success'] = 'Data Fetched Successfully.';
  }catch (MarketplaceWebServiceProducts_Exception $ex) {
    echo("Caught Exception: " . $ex->getMessage() . "\n");
    echo("Response Status Code: " . $ex->getStatusCode() . "\n");
    echo("Error Code: " . $ex->getErrorCode() . "\n");
    echo("Error Type: " . $ex->getErrorType() . "\n");
    echo("Request ID: " . $ex->getRequestId() . "\n");
    echo("XML: " . $ex->getXML() . "\n");
    echo("ResponseHeaderMetadata: " . $ex->getResponseHeaderMetadata() . "\n");
  }
}


function invokeGetCompetitivePricing(MarketplaceWebServiceProducts_Interface $service, $request, $region){
	try{
		//header('Content-Type: application/xml; charset=utf-8');
		//header("Content-type: text/xml");
		$response=$service->GetCompetitivePricingForASIN($request);
		$dom = new DOMDocument();
		$dom->loadXML($response->toXML());
		echo $dom->saveXML();
		
	}catch (MarketplaceWebServiceProducts_Exception $ex) {
    echo("Caught Exception: " . $ex->getMessage() . "\n");
    echo("Response Status Code: " . $ex->getStatusCode() . "\n");
    echo("Error Code: " . $ex->getErrorCode() . "\n");
    echo("Error Type: " . $ex->getErrorType() . "\n");
    echo("Request ID: " . $ex->getRequestId() . "\n");
    echo("XML: " . $ex->getXML() . "\n");
    echo("ResponseHeaderMetadata: " . $ex->getResponseHeaderMetadata() . "\n");
  }
}

function invokeGetLowestOfferListingsForASIN(MarketplaceWebServiceProducts_Interface $service, $request, $region){
	try{
		//header('Content-Type: application/xml; charset=utf-8');
		//header("Content-type: text/xml");
		$response=$service->getLowestOfferListingsForASIN($request);
		$dom = new DOMDocument();
		$dom->loadXML($response->toXML());
		echo $dom->saveXML();
	}catch (MarketplaceWebServiceProducts_Exception $ex) {
    echo("Caught Exception: " . $ex->getMessage() . "\n");
    echo("Response Status Code: " . $ex->getStatusCode() . "\n");
    echo("Error Code: " . $ex->getErrorCode() . "\n");
    echo("Error Type: " . $ex->getErrorType() . "\n");
    echo("Request ID: " . $ex->getRequestId() . "\n");
    echo("XML: " . $ex->getXML() . "\n");
    echo("ResponseHeaderMetadata: " . $ex->getResponseHeaderMetadata() . "\n");
  }
}

function config_service_url($region,$serviceUrl,$marketplace_id){
  $config = array (
    'ServiceURL' => $serviceUrl,
    'ProxyHost' => null,
    'ProxyPort' => -1,
    'ProxyUsername' => null,
    'ProxyPassword' => null,
    'MaxErrorRetry' => 3,
  );
  $service = new MarketplaceWebServiceProducts_Client(
    AWS_ACCESS_KEY_ID,
    AWS_SECRET_ACCESS_KEY,
    APPLICATION_NAME,
    APPLICATION_VERSION,
    $config);
	//$csvFile = file('https://docs.google.com/spreadsheets/d/e/2PACX-1vR2gY22xgcaR4JUr3naK5nXbFzw3pL_Ogn4msFRDGfVA8nILfEs-BOdxDRt2Jvhx9Yz31eAF8IfpjBn/pub?gid=0&single=true&output=csv');
	$csvFile = file('./google-doc-test.csv');
	$data = [];
	$csv_data = [];
	$i=0;
    foreach ($csvFile as $line) {
    $csv_check_data = str_getcsv($line);
        if(isset($_GET['tier'])){
          if($_GET['tier'] == $csv_check_data[2] ){
            $data[] = $csv_check_data;
          }
         }else{
           $data[] = $csv_check_data;
         }
         $i += 1;
        //echo "<h3>$line</h3>";
        //if ($i>2) break; //testing
        //continue; //testing
     }
  
     foreach($data as $csv_array_data){
       $csv_product_asin = substr($csv_array_data[0], strrpos($csv_array_data[0], '/') + 1);
       $csv_product_len = strlen($csv_product_asin);
         if($csv_product_len == 10 && !preg_match('/[^A-Za-z0-9]/', $csv_product_asin)){
         $csv_array_data[5] = $csv_product_asin;
         $csv_data[] = $csv_product_asin;
         }
       $csv_values = implode("','", $csv_array_data);
     }
     $csv_filter_array = array_filter($csv_data);
     $csv_filter_array = array_unique($csv_filter_array);
     $csv_chunk = array_chunk($csv_filter_array,10);
     $GLOBALS['csv_array'] = $data;
     $asin_array = [];
     foreach ($data as $entry) {
       if (!empty($entry[4])){
           $csv_data = explode(',', $entry[4]);
           if (!empty($csv_data)){
             $asin_data = array_map('trim', $csv_data);
             foreach($asin_data as $asin_final_data){
               $asin_len = strlen($asin_final_data);
               if($asin_len == 10 && !preg_match('/[^A-Za-z0-9]/', $asin_final_data)){
                 $asin_array[] = $asin_final_data;
               }
            }
         }
       }
     }
      //echo '<pre>read from csv file, $asin_array='; print_r($asin_array); echo "</pre>";
      //echo "<pre>###############################################</pre>";
      //$asin_array = array_merge($asin_array,$aCompAsins); //add the array of competitor asins  -John Chen 2019-09-10
      //print_r($asin_array);
      //echo "<pre>############## after merge </pre>";
      //echo '<pre>$asin_array='; print_r($asin_array); echo "</pre>";

      //echo '<pre>$asin_array='; print_r($asin_array); echo "</pre>"; exit; //for testing
     $asin_filter_array = array_filter($asin_array);
     $asin_filter_array = array_unique($asin_filter_array);
     $asin_chunk = array_chunk($asin_filter_array,10);
     //echo '<pre>$asin_chunk='; var_dump($asin_chunk); echo "</pre>";
	 //echo '<pre>$csv_chunk='; var_dump($csv_chunk); echo "</pre>";
     //print_r($csv_chunk);
     $res = array_merge($csv_chunk, $asin_chunk);
     //echo '<pre> $res='; print_r($res); echo "</pre>"; exit; //for testing
	 
     $res2 = array( 
        0=>"B00N5D7ZFW", 
        //1=>"B00O2BOZ7M", 
        //2=>"B00CHYLXLW"
    );
    //echo '<pre>$res2='; print_r($res2); echo "</pre>";

    //echo '<pre>after merge trunk arrays, $res='; var_dump($res); echo "</pre>";
    $request = new MarketplaceWebServiceProducts_Model_GetMatchingProductRequest();
    $request->setSellerId(MERCHANT_ID);
    $request->setMarketplaceId($marketplace_id);
    $asin_list= new MarketplaceWebServiceProducts_Model_ASINListType();

    foreach($res2 as $asin){
        //echo '<pre>in for loop, $asin='; var_dump($asin); echo "</pre>";
		$asin_list->setASIN($asin);
        $request->setASINList($asin_list);
        invokeGetMatchingProduct($service, $request,$region);
		//echo '<hr>';
    }
	
	//////////////////////////////////////////////////////////d
	$request = new MarketplaceWebServiceProducts_Model_GetCompetitivePricingForASINRequest();
    $request->setSellerId(MERCHANT_ID);
    $request->setMarketplaceId($marketplace_id);
    $asin_list= new MarketplaceWebServiceProducts_Model_ASINListType();

    foreach($res2 as $asin){
        //echo '<pre>in for loop, $asin='; var_dump($asin); echo "</pre>";
		$asin_list->setASIN($asin);
        $request->setASINList($asin_list);
        invokeGetCompetitivePricing($service, $request,$region);
		//echo '<hr>';
    }
	//////////////////////////////////////////////////////////
	$request = new MarketplaceWebServiceProducts_Model_GetLowestOfferListingsForASINRequest();
    $request->setSellerId(MERCHANT_ID);
    $request->setMarketplaceId($marketplace_id);
    $asin_list= new MarketplaceWebServiceProducts_Model_ASINListType();

    foreach($res2 as $asin){
        //echo '<pre>in for loop, $asin='; var_dump($asin); echo "</pre>";
		
		$asin_list->setASIN($asin);
        $request->setASINList($asin_list);
        invokeGetLowestOfferListingsForASIN($service, $request,$region);
		//echo '<hr>';
    }
}

//$url_array = array(array("region" => "us","url" => "https://mws.amazonservices.com/Products/2011-10-01","id" => "ATVPDKIKX0DER"),array("region" => "ca","url" => "https://mws.amazonservices.ca/Products/2011-10-01","id" => "A2EUQ1WTGCTBG2"), array("region" => "uk","url" => "https://mws-eu.amazonservices.com/Products/2011-10-01","id" => "A1F83G8C2ARO7P"));
$url_array = array(array("region" => "us","url" => "https://mws.amazonservices.com/Products/2011-10-01","id" => "ATVPDKIKX0DER"));
$array_length = count($url_array);
for($i = 0; $i < $array_length; $i++){
    //echo "## i=$i";
	$region = $url_array[$i]['region'];
	$serviceUrl = $url_array[$i]['url'];
    $marketplace_id = $url_array[$i]['id'];
    config_service_url($region,$serviceUrl,$marketplace_id);
	//echo '<hr>';
}

