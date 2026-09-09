"""Domain-specific application errors with safe public messages."""


class AuthenticationError(Exception):
    """Base class for expected authentication failures."""

    def __init__(self, code: str, message: str) -> None:
        super().__init__(message)
        self.code = code
        self.message = message


class InvalidCredentialsError(AuthenticationError):
    """Credentials do not identify an authentic user."""

    def __init__(self) -> None:
        super().__init__("invalid_credentials", "Invalid email or password")


class InvalidRefreshTokenError(AuthenticationError):
    """A refresh token cannot be accepted."""

    def __init__(self, code: str = "refresh_invalid") -> None:
        message = (
            "Refresh token expired, please login again"
            if code == "refresh_expired"
            else ("Invalid refresh token, please login again")
        )
        super().__init__(code, message)


class InvalidAccessTokenError(AuthenticationError):
    """An access token fails signature, claim, or token-type validation."""

    def __init__(self) -> None:
        super().__init__("access_invalid", "Invalid access token")


class TokenConfigurationError(RuntimeError):
    """JWT signing material is missing or invalid."""


class MailDeliveryError(RuntimeError):
    """A transactional message could not be configured or delivered."""


class InvalidResetTokenError(AuthenticationError):
    """A password-reset credential is absent, expired, or already consumed."""

    def __init__(self) -> None:
        super().__init__("reset_invalid", "Reset link is invalid or has expired")


class PasswordChangeError(AuthenticationError):
    """An authenticated password change cannot be completed."""

    def __init__(self, message: str, http_status: int = 400) -> None:
        super().__init__("password_change_failed", message)
        self.http_status = http_status


class AccountVerificationError(AuthenticationError):
    """An email-verification request is invalid or expired."""

    def __init__(self, code: str, message: str, http_status: int) -> None:
        super().__init__(code, message)
        self.http_status = http_status


class AccessCodeError(AuthenticationError):
    """The authenticated member's supplied access code is invalid."""

    def __init__(self) -> None:
        super().__init__("access_code_invalid", "Invalid access code")


class RateLimitExceededError(RuntimeError):
    """A bounded request key exhausted its current allowance."""

    def __init__(self, retry_after_seconds: int) -> None:
        super().__init__("Rate limit exceeded")
        self.retry_after_seconds = max(1, retry_after_seconds)


class RateLimitBackendError(RuntimeError):
    """The configured shared rate-limit backend is unavailable."""
