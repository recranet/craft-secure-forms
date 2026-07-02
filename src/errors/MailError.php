<?php

namespace recranet\secureforms\errors;

/**
 * A notification or confirmation email could not be sent. Always a real
 * error: logged, reported and shown to the user — never swallowed.
 */
class MailError extends \Exception
{
}
