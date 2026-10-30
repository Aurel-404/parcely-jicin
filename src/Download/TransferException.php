<?php

declare(strict_types=1);

namespace Parcely\Download;

/**
 * Network-level failure (no or incomplete HTTP response); worth retrying.
 */
final class TransferException extends \RuntimeException {}
