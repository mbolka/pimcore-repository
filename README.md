# <a name="pimcore-repository"></a>Pimcore Repository

This module allows for using repository pattern instead of Active Record. 

**Table of Contents**

- [Pimcore Repository](#pimcore-repository)
	- [Description](#description)
	- [Compatibility](#compatibility)
	- [Installing/Getting started](#installing)
	- [Usage/Setting up](#usage)
	- [Features](#features)
	- [Contributing](#contributing)
	- [Licencing](#licensing)

	
## <a name="description"></a>Description	
This package allows for using Repository pattern. It's design was based on Doctrine ORM. It contains Entity Manager, Unit Of Work, Default Pimcore Entity Repository.
## <a name="compatibility"></a>Compatibility	
This module is compatible with Pimcore >= 5.2 and Pimcore 6.0.

## <a name="installing"></a>Installing/Getting started	
- Add this repository to your composer json 
```
"repositories": [
    {
      "type": "vcs",
      "url": "git@github.com:mbolka/pimcore-repository.git"
    }
  ]
```
- Install Pimcore Repository via composer ```composer require mbolka/pimcore-repository:dev```
- Register repository bundle in app/AppKernel.php file

- Create a new object of class IntegrationConfiguration
```
if (class_exists('\\Bolka\\RepositoryBundle\\RepositoryBundle')) {
            $collection->addBundle(new Bolka\RepositoryBundle\RepositoryBundle);
        }
```
## <a name="usage"></a>Usage/Setting up


## <a name="features"></a>Features

## <a name="contributing"></a>Contributing

If you'd like to contribute, please fork the repository and use a feature branch. Pull requests are welcome.

## <a name="licensing"></a>Licensing
The code in this project is licensed under the GPL license.
