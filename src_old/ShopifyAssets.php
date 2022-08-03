<?php


namespace shopify;


use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

class ShopifyAssets extends AssetBundle
{
    public function init()
    {
        $this->sourcePath = '@shopify/resources';

        // define the dependencies
        $this->depends = [
            CpAsset::class,
        ];

        $this->css = [
            'main.css',
        ];

        parent::init();
    }
}
