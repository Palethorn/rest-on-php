<?php
namespace RestOnPhp\Security;

use RestOnPhp\Handler\AuthHandler;
use RestOnPhp\Metadata\XmlMetadata;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use RestOnPhp\Security\SecureUser;
use RestOnPhp\Session\JwtSessionStorage;
use RestOnPhp\Token\Extractor;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Monolog\Logger;
use Symfony\Component\HttpFoundation\RequestStack;

class Authorization {
    private $metadata, $tokenExtractor, $authHandler, $sessionStorage, $logger, $request;

    public function __construct(
        XmlMetadata $metadata, 
        Extractor $tokenExtractor, 
        AuthHandler $authHandler,
        JwtSessionStorage $sessionStorage,
        RequestStack $requestStack,
        Logger $logger 
    ) {
        $this->metadata = $metadata;
        $this->tokenExtractor = $tokenExtractor;
        $this->authHandler = $authHandler;
        $this->sessionStorage = $sessionStorage;
        $this->request = $this->requestStack->getCurrentRequest();
        $this->logger = $logger;
    }

    public function authorize($entityClass) {
        try {
            $resourceMetadata = $this->metadata->getMetadataFor($entityClass);
        } catch(ResourceNotFoundException $e) {
            $resourceMetadata = [ 'secure' => false, 'roles' => array() ];
        }

        if(isset($resourceMetadata['secure']) && $resourceMetadata['secure']) {
            $token = $this->tokenExtractor->extract($this->request);
            $user = $this->authHandler->verify($token);
            $this->sessionStorage->setUser($user);

            $this->logger->info('SECURITY_INFO', [
                'token' => $token,
                'user_id' => $user
            ]);

            if(!($user instanceof SecureUser)) {
                return;
            }

            $authorized = true;

            if(!empty($resourceMetadata['roles'])) {
                $authorized = false;
            }

            foreach($resourceMetadata['roles'] as $role) {
                if($user->hasRole($role)) {
                    $authorized = true;
                    break;
                }
            }

            if(!$authorized) {
                $this->logger->error('SECURITY_USER_UNAUTHORIZED', [
                    'token' => $token,
                    'user_id' => $user
                ]);

                throw new UnauthorizedHttpException('role', 'User does not have permission to access this resource');
            }
        }
    }
}