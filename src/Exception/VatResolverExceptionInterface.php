<?php

declare(strict_types=1);

namespace Daumedia\VatResolver\Exception;

/**
 * Every exception this library throws implements this, so a caller can catch
 * "something in tax resolution went wrong" without catching \Throwable.
 */
interface VatResolverExceptionInterface extends \Throwable
{
}
