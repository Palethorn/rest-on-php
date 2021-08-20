<?php
namespace RestOnPhp\Handler;

use JMS\Serializer\Handler\HandlerRegistryInterface;
use RestOnPhp\Handler\Response\HandlerResponse;
use RestOnPhp\Metadata\XmlMetadata;
use Symfony\Component\HttpFoundation\Response;

class DocsHandler {
    private $metadata;

    public function __construct(XmlMetadata $metadata) {
        $this->metadata = $metadata;
    }

    public function handle() {
        return new HandlerResponse(HandlerResponse::CARDINALITY_NONE, $this->metadata->getMetadataForDocs());
    }
}
