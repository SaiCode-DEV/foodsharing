<?php

namespace Foodsharing\RestApi;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Core\Pagination;
use Foodsharing\Modules\Foodsaver\FoodsaverGateway;
use Foodsharing\Modules\Foodsaver\Profile;
use Foodsharing\Modules\Message\Conversation;
use Foodsharing\Modules\Message\DTO\ChatMessage;
use Foodsharing\Modules\Message\DTO\EditChatData;
use Foodsharing\Modules\Message\DTO\MessageCollection;
use Foodsharing\Modules\Message\Message;
use Foodsharing\Modules\Message\MessageGateway;
use Foodsharing\Modules\Message\MessageTransactions;
use Foodsharing\RestApi\Models\IDList;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[OA\Tag('conversation')]
#[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in.')]
class MessageRestController extends AbstractFoodsharingRestController
{
    public const int DEFAULT_MESSAGES_LIMIT = 20;

    public function __construct(
        private readonly FoodsaverGateway $foodsaverGateway,
        private readonly MessageGateway $messageGateway,
        private readonly MessageTransactions $messageTransactions,
        protected Session $session,
    ) {
        parent::__construct($session);
    }

    #[OA\Put(summary: 'Mark conversation as read/unread')]
    #[Route('conversations/{conversationId}/read-status', methods: ['PUT'], requirements: ['conversationId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success.')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Not permitted to access this conversation.')]
    public function markConversationRead(int $conversationId, #[MapQueryParameter] bool $isRead): Response
    {
        $this->assertLoggedIn();
        if (!$this->messageGateway->mayConversation($this->session->id(), $conversationId)) {
            throw new AccessDeniedHttpException('Not permitted to access this conversation.');
        }

        $this->messageGateway->setReadStatus($conversationId, $this->session->id(), $isRead);

        return $this->respondOK();
    }

    #[OA\Get(summary: 'Get messages from a conversation')]
    #[Route('conversations/{conversationId}/messages', methods: ['GET'], requirements: ['conversationId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success.', content: new Model(type: MessageCollection::class))]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Not permitted to access this conversation.')]
    public function getConversationMessages(
        int $conversationId,
        #[MapQueryParameter(options: ['min_range' => 1])] ?int $olderThanId,
        #[MapQueryParameter(options: ['min_range' => 1])] ?int $limit
    ): Response {
        $this->assertLoggedIn();
        if (!$this->messageGateway->mayConversation($this->session->id(), $conversationId)) {
            throw new AccessDeniedHttpException('Not permitted to access this conversation.');
        }

        $limit ??= self::DEFAULT_MESSAGES_LIMIT;

        $messages = $this->messageGateway->getConversationMessages($conversationId, $limit, $olderThanId);
        $messageCollection = $this->messageTransactions->messageCollectionFromMessages($messages);

        return $this->respondOK($messageCollection);
    }

    #[OA\Get(summary: 'Get a conversation including some messages')]
    #[Route('conversations/{conversationId}', methods: ['GET'], requirements: ['conversationId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success.', content: new OA\JsonContent(properties: [
        new OA\Property(property: 'conversation', ref: new Model(type: Conversation::class)),
        new OA\Property(property: 'profiles', type: 'array', items: new OA\Items(ref: new Model(type: Profile::class))),
    ]))]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Not permitted to access this conversation.')]
    public function getConversation(
        int $conversationId,
        #[MapQueryParameter] ?int $limit,
        #[MapQueryParameter] ?bool $markAsRead,
    ): Response {
        $this->assertLoggedIn();
        if (!$this->messageGateway->mayConversation($this->session->id(), $conversationId)) {
            throw new AccessDeniedHttpException('Not permitted to access this conversation.');
        }

        if ($markAsRead) {
            $this->messageGateway->setReadStatus($conversationId, $this->session->id(), true);
        }

        $conversation = $this->messageTransactions->getConversationData($conversationId, $limit ?? self::DEFAULT_MESSAGES_LIMIT);
        $profileIDs = array_map(fn ($message) => $message->authorId, $conversation->messages);
        $profileIDs = array_merge($profileIDs, $conversation->members);
        $profileIDs = array_unique($profileIDs);
        $profiles = $this->foodsaverGateway->getProfileForUsers($profileIDs);

        return $this->respondOK([
            'conversation' => $conversation,
            'profiles' => $profiles,
        ]);
    }

    #[OA\Post(summary: 'Returns the conversation ID for a conversation between the current user and given others. The conversion is created if it does not exist yet.')]
    #[Route('conversations/lookup', methods: ['POST'])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success.', content: new OA\JsonContent(properties: [
        new OA\Property(property: 'id', type: 'integer', description: 'The conversation ID'),
    ]))]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'At least one of the members could not be found.')]
    public function getConversationId(#[MapRequestPayload] IDList $idList): Response
    {
        $this->assertLoggedIn();

        $members = $idList->ids;
        $members[] = $this->session->id();
        $members = array_unique($members);
        if (!$this->foodsaverGateway->foodsaversExist($members)) {
            throw new NotFoundHttpException('At least one of the members could not be found');
        }

        $conversationId = $this->messageGateway->getOrCreateConversation($members);

        return $this->respondOK(['id' => $conversationId]);
    }

    #[OA\Get(summary: 'Get the list of conversations for the current user')]
    #[Route('conversations', methods: ['GET'])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success.', content: new OA\JsonContent(properties: [
        new OA\Property(property: 'conversations', type: 'array', items: new OA\Items(ref: new Model(type: Conversation::class))),
        new OA\Property(property: 'profiles', type: 'array', items: new OA\Items(ref: new Model(type: Profile::class))),
    ]))]
    public function getConversations(#[MapQueryParameter] ?int $limit, #[MapQueryParameter] ?int $offset): Response
    {
        $this->assertLoggedIn();
        $pagination = Pagination::create($limit, $offset);

        $conversationsData = $this->messageTransactions->listConversationsWithProfilesForUser($this->session->id(), $pagination);

        return $this->respondOK($conversationsData);
    }

    #[OA\Post(summary: 'Send a message in a conversation')]
    #[Route('conversations/{conversationId}/messages', methods: ['POST'], requirements: ['conversationId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success.', content: new Model(type: Message::class))]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Not permitted to access this conversation.')]
    #[OA\Response(response: Response::HTTP_BAD_REQUEST, description: 'Message body cannot be empty.')]
    public function sendMessage(int $conversationId, #[MapRequestPayload] ChatMessage $chatMessage): Response
    {
        $this->assertLoggedIn();
        if (!$this->messageGateway->mayConversation($this->session->id(), $conversationId)) {
            throw new AccessDeniedHttpException('Not permitted to access this conversation.');
        }

        $body = trim($chatMessage->body);
        if (empty($body)) {
            throw new BadRequestHttpException('Message body cannot be empty');
        }
        $message = $this->messageTransactions->sendMessage($conversationId, $this->session->id(), $body);

        return $this->respondOK($message);
    }

    #[OA\Patch(summary: 'Rename a conversation')]
    #[Route('conversations/{conversationId}', methods: ['PATCH'], requirements: ['conversationId' => Requirement::POSITIVE_INT])]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success.')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Not permitted to edit this conversation.')]
    #[OA\Response(response: Response::HTTP_BAD_REQUEST, description: 'Conversation is locked and cannot be renamed.')]
    public function patchConversation(int $conversationId, #[MapRequestPayload] EditChatData $editChatData): Response
    {
        $this->assertLoggedIn();
        if (!$this->messageGateway->mayConversation($this->session->id(), $conversationId)) {
            throw new AccessDeniedHttpException('Not permitted to edit this conversation.');
        }
        if ($this->messageGateway->isConversationLocked($conversationId)) {
            throw new BadRequestHttpException('Cannot rename a locked conversation.');
        }

        $this->messageGateway->renameConversation($conversationId, $editChatData->name);

        return $this->respondOK();
    }
}
