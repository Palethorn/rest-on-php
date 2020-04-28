<?php
namespace RestOnPhp\Handler;

use RestOnPhp\Metadata\XmlMetadata;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Serializer;

class DocsHandler {
    private $serializer;
    private $metadata;

    public function __construct(Serializer $serializer, XmlMetadata $metadata) {
        $this->serializer = $serializer;
        $this->metadata = $metadata;
    }

    public function handle() {
        $docs = array();
        return new Response($this->serializer->serialize($this->metadata->getMetadataForDocs(), 'json'), 200, array(
            'Content-Type' => 'application/json'
        ));
    }
}
