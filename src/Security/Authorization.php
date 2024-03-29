<?php
namespace RestOnPhp\Security;

use Monolog\Logger;
use RestOnPhp\Handler\AuthHandler;
use RestOnPhp\Security\SecureUser;
use RestOnPhp\Metadata\XmlMetadata;
use RestOnPhp\Session\JwtSessionStorage;
use RestOnPhp\Token\TokenExtractorInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class Authorization {
    private $metadata, $tokenExtractor, $authHandler, $sessionStorage, $logger, $request;

    public function __construct(
        XmlMetadata $metadata, 
        TokenExtractorInterface $tokenExtractor, 
        AuthHandler $authHandler,
        JwtSessionStorage $sessionStorage,
        RequestStack $requestStack,
        Logger $logger 
    ) {
        $this->metadata = $metadata;
        $this->tokenExtractor = $tokenExtractor;
        $this->authHandler = $authHandler;
        $this->sessionStorage = $sessionStorage;
        $this->request = $requestStack->getCurrentRequest();
        $this->logger = $logger;
    }

    public function authorize($resource_name) {
        try {
            $resourceMetadata = $this->metadata->getMetadataFor($resource_name);
        } catch(ResourceNotFoundException $e) {
            $resourceMetadata = [ 'secure' => false, 'roles' => [] ];
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

            if($user->hasRole('SUPERADMIN')) {
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