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

    // This function is the response called by QuotePriceController when the 
    // store associated class is retrieved from database 
    public function getResponse($obj, $url, $store, $page, $extractores, $countryCode, $cobrandCode)
    {
		$quote = $obj->get('pr.quote.price'); // services for price calculating and cart info
        $cartInf = $obj->get('pr.cart.info');
    	$info = array();// array that will contain all necessary information to show in the toolbar
    	$info = $quote->getGlobalVariables($obj, $countryCode, $cobrandCode);// set global variables, cobrand, country, category, weight...
    	$info = $quote->getProductInformationFromUrl($extractores, $url, $info);// use extractors to retrieve specific informaton related to the current product

        // Get cobrand agency data
        $eCobrand = $this->em->getRepository('PRGlobalConfigurationCobrandBundle:Cobrand')->find($info['cobrand']);
        $agency = $eCobrand->getAgency()->getId();
        $info['xagencyID'] = $agency;

        // Get store controller
        //$ePage =  $this->em->getRepository('PRGlobalConfigurationStoreManagerBundle:StorePage')->find($page);
        //$storeControllerType = $ePage->getStore()->getCustomStoreController();
        
        // if extractor have found information about the product in the provided url
        if($info['match'])
        {
            // Retrieve profit margin of this cobrand / store pair
            $info['profitMargin'] = $quote->getProfitMargin($info['cobrand'], $store);
            $info = $obj->getQuotePrice($info['cobrand'], $page, $info);
        }

        // Get import categories of this agency
        $categories = $this->em->getRepository('PRGlobalConfigurationCategoryBundle:Category')->findByAgency($agency);
        
        // Get cobrand identifiers
        $cobrand = $eCobrand->getShortName();
        $country = $eCobrand->getCountry()->getCode();

        // The response type is gonna be javascript
        $response = new Response('Content', 200, array('content-type' => 'application/x-javascript'));

        // Generate user token
        $util = $obj->get('pr.action.util');
        $token =  $util->encodeToken($obj);
        $userName = $util->getUserSession($obj);

        // If there is no user logged
        if(!$token){

            // Add anonymous token cookie to response
            $response = $util->addAnonymousCookie($obj, $response);
            $token = 'pid3lo';
        }

        // Retrieve cart info
        $cartInfo = $cartInf->getInfo($obj, $info['cobrand'], $userName);

        // Check if the cobrand has an extra currency
        if($cartInfo['extraCurrencyExchangeRate'] == null){
            $extraCurrencyFinalPrice = '';
        }
        else{
            $extraCurrencyFinalPrice = '/ ' . $cartInfo['extraCurrencySign'] . ' ' .number_format($info['finalPrice'] * $cartInfo['extraCurrencyExchangeRate'],2) ;
        }
        
        // Get the addToCart route (inverse routing)
    	$route = $obj->get('router')->generate('PRGlobalConfigurationGeneralBundle_toolbar_addToCart', array('page' => $page, 'add' => true, 'token' => $token), true);

        // Get the cart route (inverse routing)
        $urlCart = $obj->get('router')->generate('cart', array('country' => $country,'cobrand' => $cobrand), true);

        // Callback route for requoting or adding to cart
        $routeCallback = $obj->get('router')->generate('PRGlobalConfigurationGeneralBundle_toolbar_callback', array('page' => $page, 'token' => $token), true);

        // Store TEMPLATE FOR TOOLBAR -----------------------------------------------------------------------------------------------
    	$toolbar = $obj->renderView('PRGlobalConfigurationQuotePriceManagerBundle:Toolbar:templatetoolbar.html.twig',
            array('country' => $country,
                  'cobrand' => $cobrand,
                  'storeName' => 'Macys',
                  'categoryID' => $info['productCategoryId']
                  ));
        // --------------------------------------------------------------------------------------------------------------------------

        // Context menu
        if(!$userName){

            // If not logged
            $toolbar = str_replace('@contextMenu', '<span><a href =\"https://www.pidelorapido.com/'.$country.'/'.$cobrand.'/login\"> Entrar </a></span>', $toolbar);
        }
        else
        {
            // If logged
            $toolbar = str_replace('@contextMenu', '<span><a href =\"https://www.pidelorapido.com/'.$country.'/'.$cobrand.'/myaccount/profile\"> Mi Cuenta </a></span>\
                <span><a href =\"https://www.pidelorapido.com/'.$country.'/'.$cobrand.'/myaccount\"> Mis Ordenes </a></span>\
                <span><a href =\"https://www.pidelorapido.com/'.$country.'/'.$cobrand.'/suggest\"> Recomendar </a></span>\
                ', $toolbar);
        }
        
        // Use template and response
        $toolbar = str_replace('@route-callback', $routeCallback, $toolbar);
        $toolbar = str_replace('@countryCode', $info['countryCode'], $toolbar);
        $toolbar = str_replace('@cobrandCode', $info['cobrandCode'], $toolbar);
		$toolbar = str_replace('@quoter', $quoter, $toolbar);
        $toolbar = str_replace('@importTax','(' . $info['productImportTax'] * 100 . '%)', $toolbar);
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
        $response->setContent(utf8_decode($toolbar));

        return $response;
    }

    // Avoid bad interpretations of price string
    private function preformatPrice($pri){
        if(strlen($pri) >= 3){
            if($pri[strlen($pri) - 3] == ',' || 
                $pri[strlen($pri) - 3] == '.'){
                $pri[strlen($pri) - 3] = '*';
                $pri = str_replace(',', '', $pri);
                $pri = str_replace('.', '', $pri);
                $pri = str_replace(' ', '', $pri);
                $pri = str_replace('*', '.', $pri);
            }
        }
        return $pri;
    }

    // this function recieves callback for requoting or adding to cart
    public function getResponseByCallBack($obj, $url, $store, $page, $extractores, $request, $addToCart, $userName, $token, $countryCode, $cobrandCode)
    {

        $quote = $obj->get('pr.quote.price'); // services for price calculating and cart info
        $cartInf = $obj->get('pr.cart.info');
        $info = array();// array that will contain all necessary information to show in the toolbar
        $info = $quote->getGlobalVariables($obj, $countryCode, $cobrandCode);// set global variables, cobrand, country, category, weight...
        $info = $quote->getProductInformationFromUrl($extractores, $url, $info);// use extractors to retrieve specific informaton related to the current product

        // Get cobrand agency data
        $eCobrand = $this->em->getRepository('PRGlobalConfigurationCobrandBundle:Cobrand')->find($info['cobrand']);
        $agency = $eCobrand->getAgency()->getId();
        $info['xagencyID'] = $agency;

        // Callback processing
        $route = '';
        $toolbar = false;
        $productPrice = false;
        $productDescription = false;

        // If the request sent productPrice
        if($request->request->get('productPrice'))
        {
            $sanitizer = $obj->get('pr.sanitizing');

            // Get price and description from request
            $productPrice = $request->request->get('productPrice', 0.0);
            $productDescription = $request->request->get('productDescription', '');
            $productImage = $request->request->get('productImage', '');
            $productDescription = $sanitizer->clean($productDescription);
            $productDescription = $sanitizer->remove($productDescription);
            $productDescription = $sanitizer->cleanDescription($productDescription);
            
            // Check if name changed
            if($info['productName'] == '')
            {
                $info['productName'] = $request->request->get('productName', '');
                $info['productName'] = $sanitizer->clean($info['productName']);
                $info['productName'] = $sanitizer->remove($info['productName']);
            }
        }

        // The product image is not empty?
        if($productImage)
        {
            $info['productImage'] = $productImage;
        }

        // Price changed?
        if($productPrice)
        {
            // Format price
            $productPrice = $this->preformatPrice($productPrice);
            preg_match('/[0-9.\,]{1,}/', $productPrice, $matches);
            if($matches)
            {
                $productPrice = number_format(rtrim(ltrim($matches[0])), 2, '.', '');
            }else
            {
                $productPrice = 0.0;
            }

            // Change item price
            $info['productPrice'] = $productPrice;

            // If it is requoting and not adding to cart
            if($addToCart == 0)
            {
                $route = $obj->get('router')->generate('PRGlobalConfigurationGeneralBundle_toolbar_addToCart', array('page' => $page, 'add' => true, 'token' => $token), true);
                $toolbar = $obj->renderView('PRGlobalConfigurationQuotePriceManagerBundle:Toolbar:template.callback.html.twig');
            }

            // Calculate profit margin of this store / cobrand pair
            $info['profitMargin'] = $quote->getProfitMargin($info['cobrand'], $store);
            $info = $obj->getQuotePrice($info['cobrand'], $page, $info);
            
        }

        // Save store and URL information
        $info['store'] = $store;
        $info['productUrl'] = $url;
        
        if($productPrice)
        {
            if(array_key_exists('productDescription', $info))
            {
                $info['productDescription'] .= ' Custom: ' . $productDescription;
            }else
            {
                $info['productDescription'] =  'Custom: ' . $productDescription;    
            }
        }

        // If the product is beeing added to the cart
        if($addToCart == 1)
        {
            $toolbar = $obj->renderView('PRGlobalConfigurationQuotePriceManagerBundle:Toolbar:addToCart.callback.html.twig');
            
            // If user is not logged
            if(!$userName)
            {
                // Try to get it from session (it fails in IE)
                $util = $obj->get('pr.action.util');
                $userName = $util->getUserSession($obj);    
            }
            
            // Get the order to be added to
            $order = $obj->get('pr.cart')->addItem($info['country'], $info['cobrand'], $userName, $info, $obj);
            if($order == 0)
            {
                $response = new Response('<b>Ha ocurrido un error.</b>');
            }

            // Add to Cart
            return $obj->redirect($obj->generateUrl('cart', array('country' => $info['countryCode'],'cobrand' => $info['cobrandCode'])));
        }

        // AJAX error
        if(!$toolbar)
        {
            $response = new Response('Content',200,array('content-type' => 'text/html'));
            $response->headers->set('Access-Control-Allow-Credentials', true);
            $response->headers->set("Access-Control-Allow-Origin", "*");
            $response->headers->set("Access-Control-Allow-Methods", "POST, GET");
            $response->headers->set("Access-Control-Allow-Headers", "X-CSRF-Token, X-Requested-With, Accept, Accept-Version, Content-Length, Content-MD5, Content-Type, Date, X-Api-Version");
            $response->setContent('Ha ocurrido un error.');
            return $response;
        }

        // Show cart info in ajaxed requote
        $cartInfo = $cartInf->getInfo($obj, $info['cobrand'], $userName);
        if($cartInfo['extraCurrencyExchangeRate'] == null){ // if not extra currency needed, it cleans the string
            $extraCurrencyFinalPrice = '';
        }
        else{ // in other case it formats the cart total with extra currency info
            $extraCurrencyFinalPrice = '/ ' . $cartInfo['extraCurrencySign'] . ' ' .number_format($info['finalPrice'] * $cartInfo['extraCurrencyExchangeRate'],2) ;
        }

        // Cart url (inverse routing)
        $urlCart = $obj->get('router')->generate('cart', array('country' => $info['countryCode'],'cobrand' => $info['cobrandCode']), true);

        $toolbar = str_replace('@importTax','(' . $info['productImportTax'] * 100 . '%)', $toolbar);
		$toolbar = str_replace('@category', $info['productCategory'], $toolbar);
    	$toolbar = str_replace('@price', $info['productPrice'], $toolbar);
    	$toolbar = str_replace('@weight', $info['productWeight'], $toolbar);
		$toolbar = str_replace('@finalPrice', number_format($info['finalPrice'] * $info['exchangeRate'], 2) . $extraCurrencyFinalPrice, $toolbar);
		$toolbar = str_replace('@sign', $info['cobrandCurrencySign'], $toolbar);
        $toolbar = str_replace('@action', $route, $toolbar);
        $toolbar = str_replace('@URLCart', $urlCart, $toolbar);

        // Prepare the response for xdomain injection
		$response = new Response('Content', 200, array('content-type' => 'text/html'));
        $response->headers->set('Access-Control-Allow-Credentials', true);
        $response->headers->set("Access-Control-Allow-Origin", "*");
        $response->headers->set("Access-Control-Allow-Methods", "POST, GET");
        $response->headers->set("Access-Control-Allow-Headers", "X-CSRF-Token, X-Requested-With, Accept, Accept-Version, Content-Length, Content-MD5, Content-Type, Date, X-Api-Version");
        $response->setContent($toolbar);

        return $response;
    }
}
