# Image plugin for CakePHP 3

[![Latest Stable Version](https://poser.pugx.org/karolak/cakephp-image/v/stable.svg)](https://packagist.org/packages/karolak/cakephp-image)
[![Total Downloads](https://poser.pugx.org/karolak/cakephp-image/downloads.svg)](https://packagist.org/packages/karolak/cakephp-image)
[![License](https://poser.pugx.org/karolak/cakephp-image/license.svg)](https://packagist.org/packages/karolak/cakephp-image)

Plugin for fast and easy handling image uploads in CakePHP.

Image presets are generated using [WideImage](https://github.com/smottt/WideImage). See the sourceforge [documentation](http://wideimage.sourceforge.net/) page.

## Installation

You can install this plugin into your CakePHP application using [composer](http://getcomposer.org).

The recommended way to install composer packages is:

```
$ composer require karolak/cakephp-image
```

or manually add this line to your "require" key in composer.json file:
```javascript
"require": {
    ...
    "karolak/cakephp-image": "dev-master"
}
```
and run in console:
```
$ composer update
```

Next in your bootstrap.php file add line (to enable plugin in your app):
```php
Plugin::load('Image', ['bootstrap' => false, 'routes' => false]);
```

## Database preparation

To create table "images" in your database for storing informations about uploaded images, run this command:
```
$ bin/cake migrations migrate -p Image
```

## Behavior configuration parameters

* **fields**: Input fields used for images, should be the name of the file input field as key and the type as value (many, one)
* **presets**: Array of presets containing a list of WideImage methods and their parameters
* **path**: The base path where the uploaded images should be stored
* **quality**: Image quality for all presets (integer from 1 to 100)
* **table**: Table name for storing informactions about images.

## Usage

Before you add a file upload field to a form, you must first make sure that the form enctype is set to “multipart/form-data”:
```php
echo $this->Form->create($document, ['enctype' => 'multipart/form-data']);
// OR
echo $this->Form->create($document, ['type' => 'file']);
```

Next add one or more file inputs:
```php
echo $this->Form->input('photos', ['type' => 'file']);
```

Now you have to configure behavior in your table class. For example, add this to your initialize method:
```php
public function initialize(array $config)
{
  $this->addBehavior('Image.Image', [
      'path' => Configure::read('App.wwwRoot').Configure::read('App.imageBaseUrl').'uploads',
      'presets' => [
          'small' => [
              'resize' => [200, 200, 'outside', 'any'],
              'crop' => ['center', 'center', 200, 200]
          ],
          'big' => [
              'resize' => [1000, 1000, 'inside', 'any']
          ]
      ],
      'fields' => [
          'photos' => 'many'
      ],
      'quality' => 80
  ]);
}
```

## Validation

To check uploaded images use Cake's standard validation methods (in 3.0 version they add some new file validation rules).

## Receiving images

```php
$document = $this->Documents->get($id, ['contain' => ['Images_DocumentsPhotos']]);
```
This example assume that your table is called **Documents** and form field name used to upload images was **photos**.

## Helper

You can use helper to show images from presets. Just add this code to AppView initialize method:
```php
public function initialize()
{
    if(Plugin::loaded('Image')) {
        $this->loadHelper('Image.Image');
    }
}
```
Now to render img tag with image from preset use:
```php
echo $this->Image->render($image); // original file
echo $this->Image->render($image, ['preset' => 'small']); // image file from "small" preset
echo $this->Image->render($image, ['preset' => 'big', 'alt' => 'Cool image']); // image file from "big" preset + img attributes
```

Or you can just get image url:
```php
echo $this->Image->url($image);
```

## TODO
* Extend documentation
* Shell script to regenerate all preset images
* Write tests
