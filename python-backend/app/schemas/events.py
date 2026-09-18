"""Public and administrative contracts for the reviewed events table."""

from __future__ import annotations

from datetime import date, datetime, time
from typing import Literal

from pydantic import (
    AliasChoices,
    BaseModel,
    ConfigDict,
    Field,
    field_validator,
    model_validator,
)

from app.schemas.auth import StatusResponse

EventVisibility = Literal["public", "members", "private"]
EventStatus = Literal["upcoming", "active", "cancelled", "completed", "draft"]
RsvpStatus = Literal["going", "maybe", "not_going"]
EventFormQuestionType = Literal[
    "short_answer", "long_answer", "multiple_choice", "checkbox", "dropdown"
]
EventFormAction = Literal[
    "update_form",
    "delete_form",
    "add_question",
    "update_question",
    "delete_question",
    "reorder_questions",
    "upsert",
    "archive",
    "reorder_forms",
]


class EventFilters(BaseModel):
    model_config = ConfigDict(extra="ignore")

    id: int | None = Field(default=None, gt=0)
    chapter_id: int | None = Field(default=None, gt=0)
    year: str | None = Field(default=None, pattern=r"^\d{4}$")
    status: EventStatus | None = None
    limit: int = Field(default=100, ge=1, le=100)
    offset: int = Field(default=0, ge=0, le=100_000)


class EventItem(BaseModel):
    id: int
    title: str
    description: str | None = None
    event_banner: str | None = None
    status: EventStatus
    location: str | None = None
    start_date: date | None = None
    end_date: date | None = None
    start_time: time | None = None
    end_time: time | None = None
    color: str | None = None
    chapter_id: int | None = None
    chapter_name: str | None = None
    year: str | None = None
    visibility: EventVisibility | None = None
    max_attendees: int | None = None
    created_at: datetime | None = None
    updated_at: datetime | None = None
    created_by_name: str | None = None
    attendee_count: int = 0
    registration_form_count: int = 0
    has_registration_questions: bool = False


class EventListResponse(StatusResponse):
    events: list[EventItem]
    total: int
    limit: int
    offset: int


class EventDetailResponse(StatusResponse):
    event: EventItem


class EventMutationResponse(StatusResponse):
    event: EventItem | None = None


class EventCreateRequest(BaseModel):
    model_config = ConfigDict(extra="ignore")

    title: str = Field(min_length=1, max_length=250)
    description: str | None = Field(default=None, max_length=100_000)
    location: str | None = Field(default=None, max_length=255)
    start_date: date
    end_date: date | None = None
    start_time: time | None = None
    end_time: time | None = None
    color: str = Field(default="#0077cc", max_length=20)
    status: EventStatus = "upcoming"
    visibility: EventVisibility = "public"
    max_attendees: int = Field(default=0, ge=0, le=1_000_000)
    chapter_id: int | None = Field(default=None, gt=0)
    year: str | None = Field(default=None, pattern=r"^\d{4}$")

    @field_validator("title")
    @classmethod
    def title_not_blank(cls, value: str) -> str:
        value = value.strip()
        if not value:
            raise ValueError("Title must not be blank")
        return value


class EventUpdateRequest(BaseModel):
    model_config = ConfigDict(extra="ignore")

    function_type: Literal["update"]
    id: int = Field(gt=0)
    title: str | None = Field(default=None, min_length=1, max_length=250)
    description: str | None = Field(default=None, max_length=100_000)
    location: str | None = Field(default=None, max_length=255)
    start_date: date | None = None
    end_date: date | None = None
    start_time: time | None = None
    end_time: time | None = None
    color: str | None = Field(default=None, max_length=20)
    status: EventStatus | None = None
    visibility: EventVisibility | None = None
    max_attendees: int | None = Field(default=None, ge=0, le=1_000_000)
    chapter_id: int | None = Field(default=None, gt=0)
    year: str | None = Field(default=None, pattern=r"^\d{4}$")
    remove_banner: bool = False


class EventDeleteRequest(BaseModel):
    model_config = ConfigDict(extra="forbid")

    function_type: Literal["delete"]
    id: int = Field(gt=0)


class EventRegistrationRequest(BaseModel):
    """A current member's own RSVP; legacy caller-selected users are ignored."""

    model_config = ConfigDict(extra="ignore")

    event_id: int = Field(gt=0)
    status: RsvpStatus = "going"
    additional_info: str | None = Field(default=None, max_length=5_000)


class EventRsvpManageRequest(BaseModel):
    """Allow a member to cancel or update only their own existing RSVP."""

    model_config = ConfigDict(extra="ignore")

    event_id: int = Field(gt=0)
    function_type: Literal["cancel", "update"]
    status: RsvpStatus | None = None
    additional_info: str | None = Field(default=None, max_length=5_000)

    @model_validator(mode="after")
    def update_requires_status(self) -> EventRsvpManageRequest:
        if self.function_type == "update" and self.status is None:
            raise ValueError("status is required when updating an RSVP")
        return self


class EventAttendeeFilters(BaseModel):
    """Bounded event-administrator view of attendees and aggregate RSVP state."""

    model_config = ConfigDict(extra="ignore")

    event_id: int = Field(gt=0)
    status: RsvpStatus | None = None
    year: str | None = Field(default=None, pattern=r"^\d{4}$")
    limit: int = Field(default=100, ge=1, le=100)
    offset: int = Field(default=0, ge=0, le=100_000)


class EventRsvp(BaseModel):
    id: int
    event_id: int
    user_id: int
    year: str | None = None
    status: RsvpStatus
    additional_info: str | None = None
    registered_at: datetime | None = None


class EventRsvpResponse(StatusResponse):
    rsvp: EventRsvp | None = None


class EventAttendeeEvent(BaseModel):
    id: int
    title: str
    start_date: date | None = None
    event_date: date | None = None
    year: str | None = None


class EventAttendee(BaseModel):
    attendee_id: int
    user_id: int
    fullname: str | None = None
    email: str | None = None
    phone: str | None = None
    avatar: str | None = None
    status: RsvpStatus
    year: str | None = None
    additional_info: str | None = None
    registered_at: datetime | None = None


class EventAttendeeSummary(BaseModel):
    going: int = 0
    maybe: int = 0
    not_going: int = 0
    total: int = 0


class EventAttendeeListResponse(StatusResponse):
    event: EventAttendeeEvent
    summary: EventAttendeeSummary
    attendees: list[EventAttendee]
    total: int
    limit: int
    offset: int


class EventRegistrationQuestionInput(BaseModel):
    """Bounded question definition compatible with the reviewed MySQL form tables."""

    model_config = ConfigDict(extra="ignore", populate_by_name=True)

    id: int | None = Field(default=None, gt=0)
    label: str = Field(min_length=1, max_length=500)
    type: EventFormQuestionType
    required: bool = False
    placeholder: str | None = Field(default=None, max_length=255)
    options: list[str] | None = Field(default=None, max_length=100)
    max_selections: int | None = Field(
        default=None,
        ge=1,
        le=100,
        validation_alias=AliasChoices("max_selections", "maxSelections"),
    )
    sort_order: int | None = Field(
        default=None, ge=0, le=65_535, validation_alias=AliasChoices("sort_order", "order")
    )

    @field_validator("label", "placeholder", mode="before")
    @classmethod
    def trim_text(cls, value: object) -> object:
        return value.strip() if isinstance(value, str) else value

    @field_validator("options")
    @classmethod
    def normalize_options(cls, value: list[str] | None) -> list[str] | None:
        if value is None:
            return None
        normalized = [item.strip() for item in value if isinstance(item, str) and item.strip()]
        if len(normalized) != len(value) or len(set(normalized)) != len(normalized):
            raise ValueError("Options must be non-empty and unique")
        if any(len(item) > 255 for item in normalized):
            raise ValueError("Options must be at most 255 characters")
        return normalized

    @model_validator(mode="after")
    def validate_choice_options(self) -> EventRegistrationQuestionInput:
        choices = {"multiple_choice", "checkbox", "dropdown"}
        if self.type in choices and (self.options is None or len(self.options) < 2):
            raise ValueError("Choice questions require at least two options")
        if self.type not in choices and self.options not in (None, []):
            raise ValueError("Text questions cannot define options")
        if self.type != "checkbox" and self.max_selections is not None:
            raise ValueError("Only checkbox questions may define max_selections")
        if (
            self.max_selections is not None
            and self.options is not None
            and self.max_selections > len(self.options)
        ):
            raise ValueError("max_selections cannot exceed available options")
        return self


class EventRegistrationFormCreateRequest(BaseModel):
    model_config = ConfigDict(extra="ignore")

    event_id: int = Field(gt=0)
    name: str = Field(min_length=1, max_length=255)
    description: str | None = Field(default=None, max_length=65_535)
    sort_order: int = Field(default=0, ge=0, le=255)
    questions: list[EventRegistrationQuestionInput] = Field(min_length=1, max_length=100)

    @field_validator("name", "description", mode="before")
    @classmethod
    def trim_text(cls, value: object) -> object:
        return value.strip() if isinstance(value, str) else value


class EventRegistrationFormManageRequest(BaseModel):
    """One bounded mutation envelope for the legacy form-management route."""

    model_config = ConfigDict(extra="ignore", populate_by_name=True)

    action: EventFormAction = Field(validation_alias=AliasChoices("action", "function_type"))
    event_id: int | None = Field(
        default=None, gt=0, validation_alias=AliasChoices("event_id", "eventId")
    )
    form_id: int | None = Field(
        default=None, gt=0, validation_alias=AliasChoices("form_id", "formId")
    )
    question_id: int | None = Field(
        default=None, gt=0, validation_alias=AliasChoices("question_id", "questionId")
    )
    name: str | None = Field(default=None, min_length=1, max_length=255)
    description: str | None = Field(default=None, max_length=65_535)
    sort_order: int | None = Field(
        default=None, ge=0, le=255, validation_alias=AliasChoices("sort_order", "sortOrder")
    )
    is_active: bool | None = Field(
        default=None, validation_alias=AliasChoices("is_active", "isActive")
    )
    question: EventRegistrationQuestionInput | None = None
    questions: list[EventRegistrationQuestionInput] | None = Field(default=None, max_length=100)
    order: list[int] | None = Field(default=None, max_length=100)
    forms: list[EventRegistrationFormOrder] | None = Field(default=None, max_length=20)

    @field_validator("name", "description", mode="before")
    @classmethod
    def trim_text(cls, value: object) -> object:
        return value.strip() if isinstance(value, str) else value

    @model_validator(mode="after")
    def validate_action(self) -> EventRegistrationFormManageRequest:
        requires_form = {
            "update_form",
            "delete_form",
            "add_question",
            "update_question",
            "delete_question",
            "reorder_questions",
            "archive",
        }
        if self.action in requires_form and self.form_id is None:
            raise ValueError("form_id is required")
        if self.action == "upsert" and (
            self.event_id is None or self.name is None or not self.questions
        ):
            raise ValueError("event_id, name, and questions are required for upsert")
        if self.action == "add_question" and self.question is None:
            raise ValueError("question is required when adding a question")
        if self.action in {"update_question", "delete_question"} and self.question_id is None:
            raise ValueError("question_id is required")
        if self.action == "update_question" and self.question is None:
            raise ValueError("question is required when updating a question")
        if self.action == "reorder_questions" and not self.order:
            raise ValueError("order is required when reordering questions")
        if self.action == "reorder_forms" and (self.event_id is None or not self.forms):
            raise ValueError("event_id and forms are required when reordering forms")
        return self


class EventRegistrationFormOrder(BaseModel):
    model_config = ConfigDict(extra="ignore", populate_by_name=True)

    form_id: int = Field(gt=0, validation_alias=AliasChoices("form_id", "formId"))
    sort_order: int = Field(ge=0, le=255, validation_alias=AliasChoices("sort_order", "sortOrder"))


class EventRegistrationFormsRequest(BaseModel):
    model_config = ConfigDict(extra="ignore", populate_by_name=True)

    event_id: int = Field(gt=0, validation_alias=AliasChoices("event_id", "eventId"))
    include_inactive: bool = Field(default=False)


class EventRegistrationAnswerInput(BaseModel):
    model_config = ConfigDict(extra="ignore", populate_by_name=True)

    form_id: int = Field(gt=0, validation_alias=AliasChoices("form_id", "formId"))
    question_id: int = Field(gt=0, validation_alias=AliasChoices("question_id", "questionId"))
    answer: str | list[str] | None = Field(
        default=None, validation_alias=AliasChoices("answer", "value")
    )


class EventRegistrationAnswerGroup(BaseModel):
    model_config = ConfigDict(extra="ignore", populate_by_name=True)

    form_id: int = Field(gt=0, validation_alias=AliasChoices("form_id", "formId"))
    answers: list[EventRegistrationAnswerInput] = Field(max_length=100)


class EventRegistrationWithFormsRequest(BaseModel):
    model_config = ConfigDict(extra="ignore", populate_by_name=True)

    event_id: int = Field(gt=0, validation_alias=AliasChoices("event_id", "eventId"))
    rsvp_status: RsvpStatus = Field(
        default="going", validation_alias=AliasChoices("rsvp_status", "status", "rsvpStatus")
    )
    additional_info: str | None = Field(
        default=None,
        max_length=5_000,
        validation_alias=AliasChoices("additional_info", "additionalInfo"),
    )
    answers: list[EventRegistrationAnswerInput] | None = Field(default=None, max_length=1_000)
    form_answers: list[EventRegistrationAnswerGroup] | None = Field(default=None, max_length=20)

    @field_validator("additional_info", mode="before")
    @classmethod
    def trim_note(cls, value: object) -> object:
        return value.strip() if isinstance(value, str) else value

    @model_validator(mode="after")
    def one_answer_shape(self) -> EventRegistrationWithFormsRequest:
        if self.answers is not None and self.form_answers is not None:
            raise ValueError("Use either answers or form_answers, not both")
        return self

    def normalized_answers(self) -> list[EventRegistrationAnswerInput] | None:
        if self.answers is not None:
            return self.answers
        if self.form_answers is None:
            return None
        flattened: list[EventRegistrationAnswerInput] = []
        for group in self.form_answers:
            for answer in group.answers:
                if answer.form_id != group.form_id:
                    raise ValueError("Answer form_id does not match its form_answers group")
                flattened.append(answer)
        return flattened


class EventRegistrationFormQuestion(BaseModel):
    id: int
    label: str
    type: EventFormQuestionType
    required: bool
    placeholder: str | None = None
    options: list[str] | None = None
    max_selections: int | None = None
    sort_order: int


class EventRegistrationForm(BaseModel):
    id: int
    event_id: int
    name: str
    description: str | None = None
    sort_order: int
    version: int
    is_active: bool
    questions: list[EventRegistrationFormQuestion]


class EventRegistrationFormsResponse(StatusResponse):
    event_id: int
    forms: list[EventRegistrationForm]


class EventRegistrationFormMutationResponse(StatusResponse):
    form: EventRegistrationForm | None = None


class EventRegistrationSubmissionResponse(StatusResponse):
    rsvp: EventRsvp
    answers_saved: int


class EventRegistrationSubmissionFilters(BaseModel):
    model_config = ConfigDict(extra="ignore", populate_by_name=True)

    event_id: int = Field(gt=0, validation_alias=AliasChoices("event_id", "eventId"))
    rsvp_status: RsvpStatus | None = Field(
        default=None, validation_alias=AliasChoices("rsvp_status", "status")
    )
    page: int = Field(default=1, ge=1, le=10_000)
    per_page: int = Field(default=20, ge=1, le=100)


class EventRegistrationSubmissionListItem(BaseModel):
    attendee_id: int
    user_id: int
    fullname: str | None = None
    email: str | None = None
    phone: str | None = None
    avatar: str | None = None
    rsvp_status: RsvpStatus
    additional_info: str | None = None
    registered_at: datetime | None = None
    has_form_answers: bool
    answer_count: int


class EventRegistrationSubmissionsResponse(StatusResponse):
    event_id: int
    total: int
    page: int
    per_page: int
    registrations: list[EventRegistrationSubmissionListItem]


class EventRegistrationSubmissionDetailRequest(BaseModel):
    model_config = ConfigDict(extra="ignore", populate_by_name=True)

    event_id: int = Field(gt=0, validation_alias=AliasChoices("event_id", "eventId"))
    attendee_id: int | None = Field(
        default=None, gt=0, validation_alias=AliasChoices("attendee_id", "attendeeId")
    )
    user_id: int | None = Field(
        default=None, gt=0, validation_alias=AliasChoices("user_id", "userId")
    )

    @model_validator(mode="after")
    def select_one_registration(self) -> EventRegistrationSubmissionDetailRequest:
        if (self.attendee_id is None) == (self.user_id is None):
            raise ValueError("Provide exactly one of attendee_id or user_id")
        return self


class EventRegistrationAnswerView(BaseModel):
    question_id: int
    label: str
    type: EventFormQuestionType
    required: bool
    sort_order: int
    placeholder: str | None = None
    options: list[str] | None = None
    max_selections: int | None = None
    answer: str | list[str] | None = None


class EventRegistrationSubmissionFormView(BaseModel):
    form_id: int
    form_name: str
    form_version: int
    answers: list[EventRegistrationAnswerView]


class EventRegistrationSubmissionDetail(BaseModel):
    attendee_id: int
    event_id: int
    user_id: int
    fullname: str | None = None
    email: str | None = None
    phone: str | None = None
    avatar: str | None = None
    rsvp_status: RsvpStatus
    additional_info: str | None = None
    registered_at: datetime | None = None
    forms: list[EventRegistrationSubmissionFormView]


class EventRegistrationSubmissionDetailResponse(StatusResponse):
    registration: EventRegistrationSubmissionDetail
