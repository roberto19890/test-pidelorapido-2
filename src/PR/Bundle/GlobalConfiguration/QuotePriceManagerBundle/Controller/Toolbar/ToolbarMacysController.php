<?php


namespace PR\Bundle\GlobalConfiguration\QuotePriceManagerBundle\Controller\Toolbar;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use PR\Bundle\GlobalConfiguration\CobrandBundle\Entity\Cobrand;
use Doctrine\ORM\EntityManager;
use Symfony\Component\HttpFoundation\Response;

class ToolbarMacysController extends Controller
{
protected $em;

    public function __construct(EntityManager $em) {
        $this->em = $em;
    }

    public function getResponse($obj, $url, $store, $page, $extractores, $countryCode, $cobrandCode)
    {//this function is the response called by QuotePriceController.php when the store associated class is retrieved from database 
		$quote = $obj->get('pr.quote.price');//services for price calculating and cart info
        $cartInf = $obj->get('pr.cart.info');
    	$info = array();//array that will contain all necessary information to show in the toolbar
    	$info = $quote->getGlobalVariables($obj, $countryCode, $cobrandCode);//set global variables, cobrand, country, category, weight...
    	$info = $quote->getProductInformationFromUrl($extractores, $url, $info);//use extractors to retrieve specific informaton related to the current product

        //return new Response(var_dump($info));
        //Datos de cobrand, agencia y categorias por agencia
        $eCobrand = $this->em->getRepository('PRGlobalConfigurationCobrandBundle:Cobrand')
                             ->find($info['cobrand']);
        $agency = $eCobrand->getAgency()->getId();

        $info['xagencyID'] = $agency;

        $ePage =  $this->em->getRepository('PRGlobalConfigurationStoreManagerBundle:StorePage')
                   ->find($page);
        
        $storeControllerType = $ePage->getStore()
                                    ->getCustomStoreController(); //fobPrice
        

        if($info['match'])//if extractor have found information about the product in the provided url
        {

            $info['profitMargin'] = $quote->getProfitMargin($info['cobrand'], $store);
            $info = $obj->getQuotePrice($info['cobrand'], $page, $info);
            //return $info;
        }

        //Trae los precios segun el controller de store
        //$info = $obj->get($storeControllerType)->getPrices($info);

        
        $categories = $this->em->getRepository('PRGlobalConfigurationCategoryBundle:Category')
                               ->findBy(array('agency' => $agency, 'disabled' => false));
        $cobrand = $eCobrand->getShortName();
        $country = $eCobrand->getCountry()->getCode();

        $response = new Response('Content',
                         200,
                         array('content-type' => 'application/x-javascript')
                );

        //Genera el token del usuario
        $util = $obj->get('pr.action.util');
        $token =  $util->encodeToken($obj);
        $userName = $util->getUserSession($obj);

        //No hay usuario logueado??
        if(!$token)
        {

            //Agrega la cookie de usuario anonimo al response
            $response = $util->addAnonymousCookie($obj, $response);
            $token = 'pid3lo';
        }

        //Trae total y conteo de items en arreglo
        $cartInfo = $cartInf->getInfo($obj, $info['cobrand'], $userName);
        if($cartInfo['extraCurrencyExchangeRate'] == null)
        {
            $extraCurrencyFinalPrice = '';
        }
        else
        {
            $extraCurrencyFinalPrice = '/ ' . $cartInfo['extraCurrencySign'] . ' ' .number_format($info['finalPrice'] * $cartInfo['extraCurrencyExchangeRate'],2) ;
        }
        

    	$route = $obj->get('router')
                     ->generate('PRGlobalConfigurationGeneralBundle_toolbar_addToCart', 
                                array('page' => $page, 'add' => true, 'token' => $token), true);

        $quoter = $obj->renderView('PRGlobalConfigurationQuotePriceManagerBundle:Quoter:quoter.html.twig', 
                               array('categories' => $categories, 
                                     'country'    => $country, 
                                     'cobrand'    => $cobrand,
                                     'token'      => $token));

        $quoterJs = $obj->renderView('PRGlobalConfigurationQuotePriceManagerBundle:Quoter:quoter.js.html.twig', 
                                     array( 'country' => $country, 
                                            'cobrand' => $cobrand,
                                            'token'      => $token));

        $urlCart = $obj->get('router')
                       ->generate('cart', 
                       array('country' => $country,'cobrand' => $cobrand), true);


        $routeCallback = $obj->get('router')
                     ->generate('PRGlobalConfigurationGeneralBundle_toolbar_callback', 
                                array('page' => $page, 'token' => $token), true);

    	$toolbar = $obj->renderView('PRGlobalConfigurationQuotePriceManagerBundle:Toolbar:macys.simple.html.twig',
            array('country' => $country,
                  'cobrand' => $cobrand,
                  'storeName' => 'Macys',
                  'categoryID' => $info['productCategoryId']
                  ));

        if(!$userName){
            $toolbar = str_replace('@contextMenu', '<span><a href =\"https://www.pidelorapido.com/'.$country.'/'.$cobrand.'/login\"> Entrar </a></span>', $toolbar);
        }
        else
        {
            $toolbar = str_replace('@contextMenu', '<span><a href =\"https://www.pidelorapido.com/'.$country.'/'.$cobrand.'/myaccount/profile\"> Mi Cuenta </a></span>\
                <span><a href =\"https://www.pidelorapido.com/'.$country.'/'.$cobrand.'/myaccount\"> Mis Ordenes </a></span>\
                <span><a href =\"https://www.pidelorapido.com/'.$country.'/'.$cobrand.'/suggest\"> Recomendar </a></span>\
                ', $toolbar);
        }
        
        $toolbar = str_replace('@route-callback', $routeCallback, $toolbar);
        $toolbar = str_replace('@countryCode', $info['countryCode'], $toolbar);
        $toolbar = str_replace('@cobrandCode', $info['cobrandCode'], $toolbar);
		$toolbar = str_replace('@quoter', $quoter, $toolbar);
        $toolbar = str_replace('@importTax','(' . $info['productImportTax'] * 100 . '%)', $toolbar);
		$toolbar = str_replace('@js-quoter', $quoterJs, $toolbar);
		$toolbar = str_replace('@category', $info['productCategory'], $toolbar);
    	$toolbar = str_replace('@price', $info['productPrice'], $toolbar);
    	$toolbar = str_replace('@weight', $info['productWeight'], $toolbar);
		$toolbar = str_replace('@finalPrice', number_format($info['finalPrice'] * $info['exchangeRate'], 2), $toolbar);
		$toolbar = str_replace('@sign', $info['cobrandCurrencySign'], $toolbar);
		$toolbar = str_replace('@action', $route, $toolbar);
        $toolbar = str_replace('@NumberItems', $cartInfo['cartItems'], $toolbar);
        $toolbar = str_replace('@TotalInCart', $cartInfo['cartTotal'], $toolbar);
        $toolbar = str_replace('@extraCurrency', $cartInfo['extraCurrencyTotal'], $toolbar);
        $toolbar = str_replace('@URLCart', $urlCart, $toolbar);
        $toolbar = str_replace('@ecFinalPrice', $extraCurrencyFinalPrice, $toolbar);
        //return new Response($info['countryCode'] . ' , ' . $info['cobrandCode']. ' , ' . $info['productPrice']. ' , ' . $info['productCategory']. ' , ' . $info['cobrandCurrencySign']. ' , ' . number_format($info['finalPrice'] * $info['exchangeRate'], 2));
        $response->setContent(utf8_decode($toolbar));

        return $response;
    }

    public function getResponseByCallBack($obj, $url, $store, $page, $extractores, $request, $addToCart, $userName, $token, $countryCode, $cobrandCode)
    {//this function recieves callback
        
        $quote = $obj->get('pr.quote.price');
        $cartInf = $obj->get('pr.cart.info');
        $info = array();
        $info = $quote->getGlobalVariables($obj, $countryCode, $cobrandCode);
        $info = $quote->getProductInformationFromUrl($extractores, $url, $info);

        $eCobrand = $this->em->getRepository('PRGlobalConfigurationCobrandBundle:Cobrand')
                             ->find($info['cobrand']);
        $agency = $eCobrand->getAgency()->getId();

        $info['xagencyID'] = $agency;

        $route = '';
        $toolbar = false;
        $productPrice = false;
        $productDescription = false;

        if($request->request->get('productPrice'))
        {
            $sanitizer = $obj->get('pr.sanitizing');

            //Extraer precio y descripcion de request
            $productPrice = $request->request->get('productPrice', 0.0);
            $productDescription = $request->request->get('productDescription', '');
            $productImage = $request->request->get('productImage', '');
            $productDescription = $sanitizer->clean($productDescription);
            $productDescription = $sanitizer->remove($productDescription);
            $productDescription = $sanitizer->cleanDescription($productDescription);
            

            if($info['productName'] == '')
            {
                $info['productName'] = $request->request->get('productName', '');
                $info['productName'] = $sanitizer->clean($info['productName']);
                $info['productName'] = $sanitizer->remove($info['productName']);
            }
        }

        //Trae el url de la imagen?
        if($productImage)
        {
            $info['productImage'] = $productImage;
        }

        //Trae el precio en request?
        if($productPrice)
        {
            //Extraer solo numeros del precio
            preg_match('/[0-9.\,]{1,}/', $productPrice, $matches);
            if($matches)
            {
                $matches[0] = str_replace(',', '', $matches[0]);
                $productPrice = number_format(rtrim(ltrim($matches[0])), 2, '.', '');
            }else
            {
                $productPrice = 0.0;
            }

            $info['productPrice'] = $productPrice;

            //No esta agregando a carreta?
            if($addToCart == 0)
            {
                $route = $obj->get('router')
                             ->generate('PRGlobalConfigurationGeneralBundle_toolbar_addToCart', 
                                        array('page' => $page, 'add' => true, 'token' => $token), true);

                $toolbar = $obj->renderView('PRGlobalConfigurationQuotePriceManagerBundle:Toolbar:amazon.callback.html.twig');
            }

            $info['profitMargin'] = $quote->getProfitMargin($info['cobrand'], $store);
            $info = $obj->getQuotePrice($info['cobrand'], $page, $info);
            
        }

        $info['store'] = $store;
        $info['productUrl'] = $url;
        
        if($productPrice)
        {
            //$description = $request->request->get('productDescription');
            if(array_key_exists('productDescription', $info))
            {
                $info['productDescription'] .= ' Custom: '.$productDescription;
            }else
            {
                $info['productDescription'] =  'Custom: '.$productDescription;    
            }
        }

        //Debe meterlo en la carreta?
        if($addToCart == 1)
        {
            $toolbar = $obj->renderView('PRGlobalConfigurationQuotePriceManagerBundle:Toolbar:addToCart.callback.html.twig');
            
            //No trae el username?
            if(!$userName)
            {
                //Prueba traerlo de la session
                $util = $obj->get('pr.action.util');
                $userName = $util->getUserSession($obj);    
            }
            
            $order = $obj->get('pr.cart')->addItem($info['country'], $info['cobrand'], $userName, $info, $obj);
            if($order == 0)
            {
                $response = new Response('<b>Ha ocurrido un error.</b>');
            }

            /*$route = $obj->get('router')
                 ->generate('cart', array('country' => $info['countryCode'], 'cobrand' => $info['cobrandCode']), true);*/

            return $obj->redirect($obj->generateUrl('cart', array('country' => $info['countryCode'],'cobrand' => $info['cobrandCode'])));
        }

        if(!$toolbar)
        {
            $response = new Response('Content',
                         200,
                         array('content-type' => 'text/html')
                );
            $response->headers->set('Access-Control-Allow-Credentials', true);
            $response->headers->set("Access-Control-Allow-Origin", "*");
            $response->headers->set("Access-Control-Allow-Methods", "POST, GET");
            $response->headers->set("Access-Control-Allow-Headers", "X-CSRF-Token, X-Requested-With, Accept, Accept-Version, Content-Length, Content-MD5, Content-Type, Date, X-Api-Version");
            $response->setContent('Ha ocurrido un error.');
            return $response;
        }

        $cartInfo = $cartInf->getInfo($obj, $info['cobrand'], $userName);
        if($cartInfo['extraCurrencyExchangeRate'] == null)//si no existe extra currency hace vacia la cadena de extracurrency, de lo contrario le da formato y datos
        {
            $extraCurrencyFinalPrice = '';
        }
        else
        {
            $extraCurrencyFinalPrice = '/ ' . $cartInfo['extraCurrencySign'] . ' ' .number_format($info['finalPrice'] * $cartInfo['extraCurrencyExchangeRate'],2) ;
        }

        $urlCart = $obj->get('router')
                       ->generate('cart', 
                       array('country' => $info['countryCode'],'cobrand' => $info['cobrandCode']), true);

        $toolbar = str_replace('@importTax','(' . $info['productImportTax'] * 100 . '%)', $toolbar);
		$toolbar = str_replace('@category', $info['productCategory'], $toolbar);
    	$toolbar = str_replace('@price', $info['productPrice'], $toolbar);
    	$toolbar = str_replace('@weight', $info['productWeight'], $toolbar);
		$toolbar = str_replace('@finalPrice', number_format($info['finalPrice'] * $info['exchangeRate'], 2) . $extraCurrencyFinalPrice, $toolbar);
		$toolbar = str_replace('@sign', $info['cobrandCurrencySign'], $toolbar);
        $toolbar = str_replace('@action', $route, $toolbar);
        $toolbar = str_replace('@URLCart', $urlCart, $toolbar);

		$response = new Response('Content',
                         200,
                         array('content-type' => 'text/html')
                );
        $response->headers->set('Access-Control-Allow-Credentials', true);
        $response->headers->set("Access-Control-Allow-Origin", "*");
        $response->headers->set("Access-Control-Allow-Methods", "POST, GET");
        $response->headers->set("Access-Control-Allow-Headers", "X-CSRF-Token, X-Requested-With, Accept, Accept-Version, Content-Length, Content-MD5, Content-Type, Date, X-Api-Version");
        $response->setContent($toolbar);

        return $response;
    }
}
