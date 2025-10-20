<?php

namespace WPML\Core\SharedKernel\Component\Server\Domain;

interface RestApiStatusInterface {


  public function isEnabled(): bool;


  public function getEndpoint(): string;


}
