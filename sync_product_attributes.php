<?php
/**
 *
 * NOTICE OF LICENSE
 *
 */
error_reporting(0);
ini_set('display_errors', 'on');
include_once(dirname(__FILE__) . '/config/config.inc.php');
include_once(dirname(__FILE__) . '/init.php');

// Log levels in Prestashop
define('INFO', 1);
define('WARNING', 2);
define('ERROR', 3);
define('CRITICAL', 4);
define('PREFIX', 'sync_products_attributes');

function logAction($text) {
    echo "<br/>LOG: {$text}";
    $logString = PREFIX . ": " . strip_tags($text);
    PrestaShopLogger::addLog($logString, INFO);
}

function logWarning($text, $errCode) {
    echo "<br/>WARNING: {$text}";
    $logString = PREFIX . ": " . strip_tags($text);
    PrestaShopLogger::addLog($logString, WARNING);
}

function logError($text, $errCode) {
    echo "<br/>ERROR: {$text}";
    $logString = PREFIX . ": " . strip_tags($text);
    PrestaShopLogger::addLog($logString, ERROR, $errCode);
}


function getIdString($id) {
    return "(<strong>{$id}</strong>)";
}

function getProductString($product) {
    $id = $product['id_product'];
    $name = $product['name'];
    return getIdString($id) . ":{$name}";
}

function getProductsHtmlList($products) {
    $htmlList = "<ol>";
    foreach ($products as &$product) {
        $str = getProductString($product);
        $htmlList .= "<li>{$str},</li>";
    }
    $htmlList .= "</ol>";
    
    return $htmlList;
}


function getProductsWithPricesDrop() {
    $numberOfProductsWithPricesDrop = Product::getPricesDrop(1, 0, 0, true);
    $productsWithPricesDrop = Product::getPricesDrop(1, 0, $numberOfProductsWithPricesDrop, false);
    $productsWithPricesDrop = array_filter($productsWithPricesDrop, function($product) {
        return $product['active'];
    });
    
    usort($productsWithPricesDrop, function ($product1, $product2) {
        if ($product1['id_product'] == $product2['id_product']) return 0;
        return $product1['id_product'] < $product2['id_product'] ? -1 : 1;
    });

    $htmlList = getProductsHtmlList($productsWithPricesDrop);
    logAction("Active products with prices drop: {$htmlList}");
    
    $productsWithPricesDrop = array_map(function($product) {
       return $product['id_product'];
    }, $productsWithPricesDrop);
    
    return $productsWithPricesDrop;
}

function getProductsOnSale($products) {
    $productsOnSale = array_filter($products, function($product) {
       return $product['on_sale'] == 1 && $product['active'];
    });
        
    usort($productsOnSale, function ($product1, $product2) {
        if ($product1['id_product'] == $product2['id_product']) return 0;
        return $product1['id_product'] < $product2['id_product'] ? -1 : 1;
    });
    
    $htmlList = getProductsHtmlList($productsOnSale);
    logAction("Active products on sale: {$htmlList}");

    $productsOnSale = array_map(function($product) {
       return $product['id_product'];
    }, $productsOnSale);

    return $productsOnSale;
}


function setOnSaleValue($id, $value) {
    $newProduct = new Product((int)$id);
    $val = $value ? "Setting" : "Unsetting";
    logAction(getIdString($id) . " {$val} on-sale.");
    
    $newProduct->on_sale = $value ? 1 : 0;
    $newProduct->save();
}

function setActiveValue($id, $value) {
    $newProduct = new Product((int)$id);
    $val = $value ? "Setting" : "Unsetting";
    logAction(getIdString($id) . " {$val} active.");
    
    $newProduct->active = $value ? 1 : 0;
    $newProduct->save();
}


if (isset($_GET['secure_key'])) {
    $secureKey = md5(_COOKIE_KEY_.Configuration::get('PS_SHOP_NAME'));
    if (!empty($secureKey) && $secureKey === $_GET['secure_key']) {
        $products = Product::getProducts(1, 0, 0, 'id_product', 'DESC' );
    
        $productsWithPricesDropIds = getProductsWithPricesDrop();
        $productsOnSaleIds = getProductsOnSale($products);
        
        // Update products which are not on sale and have prices dropped.
        $productsToBeOnSaleIds = array_values(array_diff($productsWithPricesDropIds, $productsOnSaleIds));
        $nbProductsToBeOnSale = count($productsToBeOnSaleIds);
        logAction("Active products not on sale and with prices drop: {$nbProductsToBeOnSale}");
        foreach ($productsToBeOnSaleIds as &$id) {
            setOnSaleValue($id, true);
        }
        
        // Update products which are on sale and don't have prices dropped.
        $productsToBeNotOnSaleIds = array_values(array_diff($productsOnSaleIds, $productsWithPricesDropIds));
        $nbProductsToBeNotOnSale = count($productsToBeNotOnSaleIds);
        logAction("Active products on sale and without prices drop: {$nbProductsToBeNotOnSale}");
        foreach ($productsToBeNotOnSaleIds as &$id) {
            setOnSaleValue($id, false);
        }
        
        // Update products which 'quantity' and 'active' parameters are not synced
        logAction("Updating products in which quantity and active parameters are not synced.");
        foreach ($products as &$product) {
            $id = $product['id_product'];
            $quantity = Product::getRealQuantity($id);
            if ($quantity <= 0 && $product['active'] == 1) {
                logAction(getIdString($id) . " Quantity: " . $quantity . ". Active: " . $product['active']);
                setActiveValue($product['id_product'], false);
            } else if ($quantity > 0 && $product['active'] == 0) {
                logAction(getIdString($id) . " Quantity: " . $quantity . ". Active: " . $product['active']);
                setActiveValue($product['id_product'], true);
            }
        }
    } else {
        logError("Unauthorized access attempt. secure_key={$secureKey}", 1);
    }
} else {
    logError("Unauthorized access attempt.", 2);
}

return true;
