<?php
namespace Omeka\Service;

use Omeka\Api\Exception;
use Omeka\Api\Manager as ApiManager;
use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

/**
 * API manager factory.
 */
class ApiManagerFactory implements FactoryInterface
{
    /**
     * Create the API manager service.
     * 
     * @param ServiceLocatorInterface $serviceLocator
     * @return ApiManager
     */
    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        $config = $serviceLocator->get('Config');
        if (!isset($config['api_resources'])) {
            throw new Exception\ConfigException('The configuration has no registered API resources.');
        }
        $apiManager = new ApiManager;
        $apiManager->setServiceLocator($serviceLocator);
        $apiManager->registerResources($config['api_resources']);
        return $apiManager;
    }
}
