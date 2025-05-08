
# Omeka-s-module-OIDC
Omeka S module to provide OIDC authentication

[add first step- where running git clone]


## Setup
Begin by downloading the software using one of the two methods below.

Option 1: Use git clone to install the module in your Omeka-S modules folder, e.g.:
```
cd <path>/omeka-s/modules 
git clone https://github.iu.edu/RDServices/Omeka-s-module-OIDC.git OIDC
```
Option 2: Download a zip file of the module from https://github.iu.edu/RDServices/Omeka-s-module-OIDC and (if necessary) unzip it. Then move the module to your Omeka-S modules folder, e.g.:

```
mkdir -p <path>/omeka-s/modules/OIDC
cp -r <path-to-zip-file> <path>/omeka-s/modules/OIDC
```

After the module is in place, run composer to install required packages:
```
cd OIDC
composer install
```

Add the OIDC client and secret to /config/local.config.php in your Omeka installation. e.g.:
```
'oidc' => [
    'client_id' => '*****',
    'client_secret' => '*****',
],
```
