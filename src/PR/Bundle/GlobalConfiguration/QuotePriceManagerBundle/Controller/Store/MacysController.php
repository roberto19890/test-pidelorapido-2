<?php


namespace PR\Bundle\GlobalConfiguration\QuotePriceManagerBundle\Controller\Store;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use PR\Bundle\GlobalConfiguration\CobrandBundle\Entity\Cobrand;
use Doctrine\ORM\EntityManager;

class MacysController extends Controller
{
//database acces, info array, and util definition
protected $em;
protected $info;
protected $util;

    public function __construct(EntityManager $em, $util) {
        $this->em = $em;//new entity manager instance
        $this->util = $util;//new utility instance
    }

    public function getPrices($info)
    {
    	$this->info = $info;
        
        //No se están recalculando valores?
        if(!array_key_exists('pr_recalculate', $this->info))
        {
            $this->info['shippingPrice'] = 9.95;
            $this->info['productSeller'] = 'Macys';
            $this->info['productWeight'] = 1;

            //No es un templateItem?
            if(!array_key_exists('pr_templateItem', $this->info))
            {
                $defaultCategory = $this->em->getRepository('PRGlobalConfigurationCategoryBundle:Category')
                                        ->findOneBy(array('name' => 'Ropa', 'agency' => $this->info['xagencyID']));//find category fixed to cloth and based on current agencyId

                $this->info['productCategoryId'] = $defaultCategory->getId();
                $this->info['productCategory']  = $defaultCategory->getName();
                $this->info['productImportTax'] = $defaultCategory->getImportTax() / 100.0;
                $this->info['productMinWeight'] = $defaultCategory->getMinWeight();//mimnimum weight for current cobrand
            }
            else
            {
                $data = $info['productName'].' '.$info['productDescription'];
                $this->info['label'] = $this->util->getLabel($data);
            }

            //No trae imagen?
            if(!array_key_exists('productImage', $this->info))
            {
                $info['productImage'] = "";
            }
        }

        $this->calculateFOBPriceAction();
    	return $this->info;
    }

    private function calculateFOBPriceAction()//if price is greater than 99 adds fobprice shipping price
    {
    	$price = $this->info['productPrice'];
        if($price <= 99){
            $shippingPrice = $this->info['shippingPrice'];
        }
        else{
           $this->info['shippingPrice'] = 0.0;
        }
        $this->info['fobPrice'] = ($price + $shippingPrice) * 1.07;
    }
}