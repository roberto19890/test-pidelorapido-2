<?php


namespace PR\Bundle\GlobalConfiguration\QuotePriceManagerBundle\Controller\Store;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use PR\Bundle\GlobalConfiguration\CobrandBundle\Entity\Cobrand;
use Doctrine\ORM\EntityManager;

class MacysController extends Controller
{
// database acces, info array, and util definition
protected $em;
protected $info;
protected $util;

    public function __construct(EntityManager $em, $util) {
        $this->em = $em; // new entity manager instance
        $this->util = $util; // new utility instance
    }

    public function getPrices($info)
    {
    	$this->info = $info;

        // If not requoting
        if(!array_key_exists('pr_recalculate', $this->info))
        {

            // Store info
            $this->info['shippingPrice'] = 9.95;
            $this->info['productSeller'] = 'Macys';
            $this->info['productWeight'] = 2;

            // label product based on description and name
            $this->$info['label'] = $util->getLabel($this->info['productDescription'] . " " . $this->info['productName']);

            // Product image has been set?
            if(!array_key_exists('productImage', $this->info))
            {
                $info['productImage'] = "";
            }
        }

        $this->calculateFOBPriceAction();
    	return $this->info;
    }

    private function calculateFOBPriceAction()
    {

        // This FOB calculation uses macys rules as example ... if product price is greater
        // than 99, then modify shippingPrice to 0.00 
    	$price = $this->info['productPrice'];
        if($price <= 99){
            $shippingPrice = $this->info['shippingPrice'];
        }
        else{
           $this->info['shippingPrice'] = 0.0;
        }
        $this->info['fobPrice'] = ($price + $shippingPrice) * 1.07; // It also adds US TAXes
    }
}