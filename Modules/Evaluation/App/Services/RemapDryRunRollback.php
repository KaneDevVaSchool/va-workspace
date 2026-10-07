<?php

namespace Modules\Evaluation\App\Services;

use RuntimeException;

/** Rollback giao dịch dry-run remap — không phải lỗi nghiệp vụ. */
class RemapDryRunRollback extends RuntimeException
{
}
