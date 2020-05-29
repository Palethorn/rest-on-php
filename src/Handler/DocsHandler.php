<?php
namespace RestOnPhp\Handler;

use RestOnPhp\Metadata\XmlMetadata;
use Symfony\Component\HttpFoundation\Response;

class DocsHandler {
    private $metadata;

    public function __construct(XmlMetadata $metadata) {
        $this->metadata = $metadata;
    }

    public function handle() {
        return new Response(
            json_encode($this->metadata->getMetadataForDocs()), 
            200, 
            ['Content-Type' => 'application/json']
        );
    }
}
