"""Active frontend V2 messaging endpoints with database-fact authorization."""

from __future__ import annotations

from typing import Annotated, Any

from fastapi import APIRouter, Depends, Request
from fastapi.responses import FileResponse, JSONResponse
from pydantic import ValidationError
from starlette.concurrency import run_in_threadpool
from starlette.datastructures import UploadFile

from app.api.common import (
    body_mapping,
    enforce_rate_limit,
    json_response,
    request_body_schema,
    with_session,
)
from app.authorization.dependencies import AccessPrincipal, require_access
from app.core.config import Settings
from app.db.session import Database
from app.integrations.uploads import (
    AvatarUploadError,
    ChatAttachmentStorage,
    UploadStorageError,
    prepare_chat_attachment,
)
from app.schemas.auth import StatusResponse
from app.schemas.chat import (
    ChatAddMemberRequest,
    ChatAttachmentResponse,
    ChatDeleteRequest,
    ChatDirectRequest,
    ChatGroupRequest,
    ChatLeaveGroupRequest,
    ChatMarkDeliveredRequest,
    ChatMarkReadRequest,
    ChatMutationResponse,
    ChatPinThreadRequest,
    ChatSendRequest,
    ChatThreadRequest,
    ChatThreadResponse,
    ChatThreadsRequest,
    ChatThreadsResponse,
)
from app.services.chat import ChatError, ChatService

router = APIRouter(prefix="/chat_api", tags=["chat"])


async def _run(
    request: Request, principal: AccessPrincipal, operation: str, model: type[Any], handler: Any
) -> JSONResponse:
    try:
        payload = model.model_validate(await body_mapping(request))
    except ValidationError:
        return json_response(
            StatusResponse(status=400, message="Invalid chat request", code="chat_invalid_request"),
            400,
        )
    await enforce_rate_limit(
        request,
        operation,
        principal.user_id,
        limit=120 if operation.startswith("chat-get") else 60,
        window_seconds=5 * 60,
        unavailable_message="Messaging service is temporarily unavailable",
    )
    database: Database = request.app.state.database
    try:
        result = await run_in_threadpool(
            with_session,
            database,
            lambda session: handler(ChatService(session), principal.user_id, payload),
        )
    except ChatError as exc:
        return json_response(
            StatusResponse(status=exc.http_status, message=exc.message, code=exc.code),
            exc.http_status,
        )
    return json_response(result, 200)


@router.post(
    "/v2_get_threads",
    response_model=ChatThreadsResponse | StatusResponse,
    openapi_extra=request_body_schema(ChatThreadsRequest, required=False),
)
async def v2_get_threads(
    request: Request, principal: Annotated[AccessPrincipal, Depends(require_access)]
) -> JSONResponse:
    """List the current principal's active V2 threads as a bounded page."""
    return await _run(
        request,
        principal,
        "chat-get-threads",
        ChatThreadsRequest,
        lambda service, actor, payload: service.list_threads(actor, payload),
    )


@router.post(
    "/v2_get_thread",
    response_model=ChatThreadResponse | StatusResponse,
    openapi_extra=request_body_schema(ChatThreadRequest),
)
async def v2_get_thread(
    request: Request, principal: Annotated[AccessPrincipal, Depends(require_access)]
) -> JSONResponse:
    return await _run(
        request,
        principal,
        "chat-get-thread",
        ChatThreadRequest,
        lambda service, actor, payload: service.get_thread(actor, payload),
    )


@router.post(
    "/v2_send_message",
    response_model=ChatMutationResponse | StatusResponse,
    openapi_extra=request_body_schema(ChatSendRequest),
)
async def v2_send_message(
    request: Request, principal: Annotated[AccessPrincipal, Depends(require_access)]
) -> JSONResponse:
    return await _run(
        request,
        principal,
        "chat-send-message",
        ChatSendRequest,
        lambda service, actor, payload: service.send_message(actor, payload),
    )


@router.post(
    "/v2_send_direct",
    response_model=ChatMutationResponse | StatusResponse,
    openapi_extra=request_body_schema(ChatDirectRequest),
)
async def v2_send_direct(
    request: Request, principal: Annotated[AccessPrincipal, Depends(require_access)]
) -> JSONResponse:
    return await _run(
        request,
        principal,
        "chat-send-direct",
        ChatDirectRequest,
        lambda service, actor, payload: service.send_direct(actor, payload),
    )


@router.post(
    "/v2_create_group",
    response_model=ChatThreadResponse | StatusResponse,
    openapi_extra=request_body_schema(ChatGroupRequest),
)
async def v2_create_group(
    request: Request, principal: Annotated[AccessPrincipal, Depends(require_access)]
) -> JSONResponse:
    return await _run(
        request,
        principal,
        "chat-create-group",
        ChatGroupRequest,
        lambda service, actor, payload: service.create_group(actor, payload),
    )


@router.post(
    "/v2_delete_message",
    response_model=ChatMutationResponse | StatusResponse,
    openapi_extra=request_body_schema(ChatDeleteRequest),
)
async def v2_delete_message(
    request: Request, principal: Annotated[AccessPrincipal, Depends(require_access)]
) -> JSONResponse:
    return await _run(
        request,
        principal,
        "chat-delete-message",
        ChatDeleteRequest,
        lambda service, actor, payload: service.delete_message(actor, payload),
    )


@router.post(
    "/v2_mark_read",
    response_model=ChatMutationResponse | StatusResponse,
    openapi_extra=request_body_schema(ChatMarkReadRequest),
)
async def v2_mark_read(
    request: Request, principal: Annotated[AccessPrincipal, Depends(require_access)]
) -> JSONResponse:
    return await _run(
        request,
        principal,
        "chat-mark-read",
        ChatMarkReadRequest,
        lambda service, actor, payload: service.mark_read(actor, payload),
    )


@router.post(
    "/v2_mark_delivered",
    response_model=ChatMutationResponse | StatusResponse,
    openapi_extra=request_body_schema(ChatMarkDeliveredRequest),
)
async def v2_mark_delivered(
    request: Request, principal: Annotated[AccessPrincipal, Depends(require_access)]
) -> JSONResponse:
    return await _run(
        request,
        principal,
        "chat-mark-delivered",
        ChatMarkDeliveredRequest,
        lambda service, actor, payload: service.mark_delivered(actor, payload),
    )


@router.post(
    "/v2_add_member",
    response_model=ChatMutationResponse | StatusResponse,
    openapi_extra=request_body_schema(ChatAddMemberRequest),
)
async def v2_add_member(
    request: Request, principal: Annotated[AccessPrincipal, Depends(require_access)]
) -> JSONResponse:
    return await _run(
        request,
        principal,
        "chat-add-member",
        ChatAddMemberRequest,
        lambda service, actor, payload: service.add_member(actor, payload),
    )


@router.post(
    "/v2_leave_group",
    response_model=ChatMutationResponse | StatusResponse,
    openapi_extra=request_body_schema(ChatLeaveGroupRequest),
)
async def v2_leave_group(
    request: Request, principal: Annotated[AccessPrincipal, Depends(require_access)]
) -> JSONResponse:
    return await _run(
        request,
        principal,
        "chat-leave-group",
        ChatLeaveGroupRequest,
        lambda service, actor, payload: service.leave_group(actor, payload),
    )


@router.post(
    "/v2_pin_thread",
    response_model=ChatMutationResponse | StatusResponse,
    openapi_extra=request_body_schema(ChatPinThreadRequest),
)
async def v2_pin_thread(
    request: Request, principal: Annotated[AccessPrincipal, Depends(require_access)]
) -> JSONResponse:
    return await _run(
        request,
        principal,
        "chat-pin-thread",
        ChatPinThreadRequest,
        lambda service, actor, payload: service.pin_thread(actor, payload),
    )


async def _attachment_input(request: Request) -> tuple[int | None, int | None, UploadFile | None]:
    """Accept only the frontend's one-file multipart contract."""
    form = await request.form()
    thread_id: int | None = None
    recipient_id: int | None = None
    upload: UploadFile | None = None
    for key, value in form.multi_items():
        if key == "thread_id" and isinstance(value, str):
            try:
                thread_id = int(value)
            except ValueError:
                thread_id = None
        elif key == "recipient_id" and isinstance(value, str):
            try:
                recipient_id = int(value)
            except ValueError:
                recipient_id = None
        elif key == "file" and isinstance(value, UploadFile) and upload is None:
            upload = value
    return thread_id, recipient_id, upload


@router.post("/v2_upload_attachment", response_model=ChatAttachmentResponse | StatusResponse)
async def v2_upload_attachment(
    request: Request, principal: Annotated[AccessPrincipal, Depends(require_access)]
) -> JSONResponse:
    """Stage a content-validated private attachment owned by the current account."""
    thread_id, recipient_id, upload = await _attachment_input(request)
    if (
        upload is None
        or (thread_id is not None and thread_id <= 0)
        or (recipient_id is not None and recipient_id <= 0)
    ):
        return json_response(
            StatusResponse(
                status=400,
                message="A file and valid thread_id or recipient_id are required",
                code="chat_attachment_invalid_request",
            ),
            400,
        )
    try:
        content = await upload.read(2 * 1024 * 1024 + 1)
        prepared = await run_in_threadpool(prepare_chat_attachment, upload.filename, content)
    except AvatarUploadError as exc:
        return json_response(
            StatusResponse(status=400, message=str(exc), code="chat_attachment_invalid_content"),
            400,
        )
    finally:
        await upload.close()
    await enforce_rate_limit(
        request,
        "chat-upload-attachment",
        principal.user_id,
        limit=30,
        window_seconds=5 * 60,
        unavailable_message="Messaging service is temporarily unavailable",
    )
    database: Database = request.app.state.database
    settings: Settings = request.app.state.settings
    try:
        result = await run_in_threadpool(
            with_session,
            database,
            lambda session: ChatService(
                session, ChatAttachmentStorage(settings.upload_root)
            ).stage_attachment(
                principal.user_id,
                prepared,
                thread_id=thread_id,
                recipient_id=recipient_id,
            ),
        )
    except ChatError as exc:
        return json_response(
            StatusResponse(status=exc.http_status, message=exc.message, code=exc.code),
            exc.http_status,
        )
    except UploadStorageError:
        return json_response(
            StatusResponse(
                status=503,
                message="Attachment storage is temporarily unavailable",
                code="chat_attachment_storage_unavailable",
            ),
            503,
        )
    return json_response(result, 200)


@router.get("/v2_attachments/{attachment_id}", response_model=None)
async def v2_get_attachment(
    attachment_id: int,
    request: Request,
    principal: Annotated[AccessPrincipal, Depends(require_access)],
) -> FileResponse | JSONResponse:
    """Return private bytes only after current participant/owner authorization."""
    await enforce_rate_limit(
        request,
        "chat-get-attachment",
        principal.user_id,
        limit=120,
        window_seconds=5 * 60,
        unavailable_message="Messaging service is temporarily unavailable",
    )
    database: Database = request.app.state.database
    settings: Settings = request.app.state.settings
    try:
        attachment = await run_in_threadpool(
            with_session,
            database,
            lambda session: ChatService(session).retrieve_attachment(
                principal.user_id, attachment_id
            ),
        )
        target = ChatAttachmentStorage(settings.upload_root).path(str(attachment["storage_path"]))
    except ChatError as exc:
        return json_response(
            StatusResponse(status=exc.http_status, message=exc.message, code=exc.code),
            exc.http_status,
        )
    except UploadStorageError:
        return json_response(
            StatusResponse(
                status=404, message="Attachment not found", code="chat_attachment_not_found"
            ),
            404,
        )
    if not target.is_file():
        return json_response(
            StatusResponse(
                status=404, message="Attachment not found", code="chat_attachment_not_found"
            ),
            404,
        )
    return FileResponse(
        target,
        media_type=str(attachment.get("mime_type") or "application/octet-stream"),
        filename=str(attachment.get("file_name") or "attachment"),
        content_disposition_type="attachment",
        headers={"Cache-Control": "private, no-store", "X-Content-Type-Options": "nosniff"},
    )


@router.post("/v2_sync_year_groups", response_model=StatusResponse)
async def v2_sync_year_groups(
    request: Request, principal: Annotated[AccessPrincipal, Depends(require_access)]
) -> JSONResponse:
    """Keep the legacy bulk membership write unavailable pending an internal-job policy."""
    await enforce_rate_limit(
        request,
        "chat-sync-year-groups",
        principal.user_id,
        limit=10,
        window_seconds=5 * 60,
        unavailable_message="Messaging service is temporarily unavailable",
    )
    return json_response(
        StatusResponse(
            status=410,
            message="Graduation-year group synchronization requires an approved internal job",
            code="chat_year_sync_unavailable",
        ),
        410,
    )


@router.post("/get_threads", response_model=StatusResponse)
@router.post("/get_thread", response_model=StatusResponse)
@router.post("/create_group", response_model=StatusResponse)
@router.post("/send_message", response_model=StatusResponse)
@router.post("/send_direct", response_model=StatusResponse)
@router.post("/list_messages", response_model=StatusResponse)
@router.post("/delete_message", response_model=StatusResponse)
@router.post("/mark_read", response_model=StatusResponse)
@router.post("/add_member", response_model=StatusResponse)
@router.post("/leave_group", response_model=StatusResponse)
@router.post("/pin_thread", response_model=StatusResponse)
@router.post("/upload_media", response_model=StatusResponse)
async def retired_v1_chat(
    request: Request, principal: Annotated[AccessPrincipal, Depends(require_access)]
) -> JSONResponse:
    """Retire noncanonical legacy tables until an approved reconciliation exists."""
    await enforce_rate_limit(
        request,
        "chat-retired-v1",
        principal.user_id,
        limit=30,
        window_seconds=5 * 60,
        unavailable_message="Messaging service is temporarily unavailable",
    )
    return json_response(
        StatusResponse(
            status=410,
            message="Legacy chat is unavailable; use the reviewed V2 messaging contract",
            code="chat_legacy_unavailable",
        ),
        410,
    )
