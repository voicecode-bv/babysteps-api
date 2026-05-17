<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Gegooid door SocialAccountLinker als een OAuth-login probeert te koppelen
 * aan een bestaand lokaal account waarvan het e-mailadres niet is
 * geverifieerd. Voorkomt account-takeover via e-mailmatching: een aanvaller
 * die een account met een vreemd e-mailadres registreert (en de mail nooit
 * verifieert) zou anders bij OAuth-login van het echte slachtoffer
 * automatisch worden gelinkt aan dat squatted account.
 */
class UnverifiedAccountLinkException extends RuntimeException {}
