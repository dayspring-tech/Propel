<?php

class SchemaPlatform
{
    public function supportsSchemas(): bool {return true;}
}

class NoSchemaPlatform
{
    public function supportsSchemas(): bool {return false;}
}
