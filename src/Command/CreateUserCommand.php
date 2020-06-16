<?php
namespace RestOnPhp\Command;

use Doctrine\ORM\EntityManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class CreateUserCommand extends Command {
    protected static $defaultName = 'api:create-user';
    private $entityManager;
    private $user;

    protected function configure() {
        $this->addArgument('username', InputArgument::REQUIRED, 'The username of the user.');
        $this->addArgument('password', InputArgument::REQUIRED, 'The password of the user.');
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        if(!$this->user) {
            throw new RuntimeException('User object could not be instantiated. Check user entity class.');
        }

        $username = $input->getArgument('username');
        $password = $input->getArgument('password');
        
        $password = password_hash($password, PASSWORD_BCRYPT, array(
            'cost' => 15
        ));

        $this->user->setUsername($username);
        $this->user->setPassword($password);
        $this->entityManager->persist($this->user);
        $this->entityManager->flush();

        return 0;
    }

    public function setEntityManager(EntityManager $entityManager) {
        $this->entityManager = $entityManager;
    }

    public function setUserEntity(string $entity) {
        if(!class_exists($entity)) {
            return;
        };

        $this->user = new $entity();
    }
}
