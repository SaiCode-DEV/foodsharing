<?php

namespace Foodsharing\RestApi;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Foodsaver\FoodsaverGateway;
use Foodsharing\Modules\Message\MessageGateway;
use Foodsharing\Modules\Message\MessageTransactions;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Request\ParamFetcher;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

#[OA\Tag('conversation')]
class MessageRestController extends AbstractFoodsharingRestController
{
    private readonly FoodsaverGateway $foodsaverGateway;
    private readonly MessageGateway $messageGateway;
    private readonly MessageTransactions $messageTransactions;

    public function __construct(
        FoodsaverGateway $foodsaverGateway,
        MessageGateway $messageGateway,
        MessageTransactions $messageTransactions,
        Session $session,
    ) {
        parent::__construct($session);
        $this->foodsaverGateway = $foodsaverGateway;
        $this->messageGateway = $messageGateway;
        $this->messageTransactions = $messageTransactions;
    }

    #[Rest\Post('conversations/{conversationId}/readStatus', requirements: ['conversationId' => '\d+'])]
    #[Rest\QueryParam(name: 'read', requirements: '0|1', description: 'Whether the message is read')]
    public function markConversationRead(int $conversationId, ParamFetcher $paramFetcher): Response
    {
        $this->assertLoggedIn();
        if (!$this->messageGateway->mayConversation($this->session->id(), $conversationId)) {
            throw new AccessDeniedHttpException();
        }

        $isRead = (bool)$paramFetcher->get('read');
        $this->messageGateway->setReadStatus($conversationId, $this->session->id(), $isRead);

        return $this->respondOK();
    }

    // #[Rest\Post('conversations/{conversationId}/unread', requirements: ['conversationId' => '\d+'])]
    // public function markConversationUnread(int $conversationId): Response
    // {
    //     if (!$this->session->mayRole()) {
    //         throw new UnauthorizedHttpException('');
    //     }
    //     if (!$this->messageGateway->mayConversation($this->session->id(), $conversationId)) {
    //         throw new AccessDeniedHttpException();
    //     }
    //     $this->messageGateway->markAsUnread($conversationId, $this->session->id());

    //     return $this->handleView($this->view([], 200));
    // }

    #[Rest\Get('conversations/{conversationId}/messages', requirements: ['conversationId' => '\d+'])]
    #[Rest\QueryParam(name: 'olderThanId', requirements: '\d+', nullable: true, default: null, description: 'ID of oldest already known message')]
    #[Rest\QueryParam(name: 'limit', requirements: '\d+', default: '20', description: 'Number of messages to return')]
    public function getConversationMessages(int $conversationId, ParamFetcher $paramFetcher): Response
    {
        $this->assertLoggedIn();
        if (!$this->messageGateway->mayConversation($this->session->id(), $conversationId)) {
            throw new AccessDeniedHttpException();
        }

        $limit = (int)$paramFetcher->get('limit');
        $olderThanID = $paramFetcher->get('olderThanId');
        $olderThanID = $olderThanID ? (int)$olderThanID : null;

        if ($olderThanID === null) {
            $this->messageGateway->setReadStatus($conversationId, $this->session->id(), true);
        }

        $messages = $this->messageGateway->getConversationMessages($conversationId, $limit, $olderThanID);
        $profileIDs = [];
        array_walk($messages, function ($v, $k) use (&$profileIDs) {
            $profileIDs[] = $v->authorId;
        });
        $profileIDs = array_unique($profileIDs);
        $profiles = $this->foodsaverGateway->getProfileForUsers($profileIDs);

        return $this->respondOK(['messages' => $messages, 'profiles' => array_values($profiles)]);
    }

    #[Rest\Get('conversations/{conversationId}', requirements: ['conversationId' => '\d+'])]
    #[Rest\QueryParam(name: 'messagesLimit', requirements: '\d+', default: '20', description: 'How many messages to return.')]
    public function getConversation(int $conversationId, ParamFetcher $paramFetcher): Response
    {
        $this->assertLoggedIn();
        if (!$this->messageGateway->mayConversation($this->session->id(), $conversationId)) {
            throw new AccessDeniedHttpException();
        }

        $messagesLimit = $paramFetcher->get('messagesLimit');

        $conversationData = $this->getConversationData($conversationId, $messagesLimit);

        return $this->respondOK($conversationData);
    }

    private function getConversationData(int $conversationId, int $messagesLimit): array
    {
        $members = $this->messageGateway->getMembersForConversations([$conversationId])[$conversationId];
        $messages = $this->messageGateway->getConversationMessages($conversationId, $messagesLimit);
        $this->messageGateway->setReadStatus($conversationId, $this->session->id(), true);
        $conversation = $this->messageGateway->getConversationForUser($conversationId, $this->session->id());
        $conversation->messages = $messages;
        $conversation->members = $members;

        $profileIDs = [];
        array_walk($messages, function ($v, $k) use (&$profileIDs) {
            $profileIDs[] = $v->authorId;
        });
        $profileIDs = array_merge($profileIDs, $members);
        $profileIDs = array_unique($profileIDs);
        $profiles = $this->foodsaverGateway->getProfileForUsers($profileIDs);

        /*
         * conversation title is not generated here so the frontend can do this including more markup (e.g. links to profiles)
         */
        return [
            'conversation' => $conversation,
            'profiles' => array_values($profiles),
        ];
    }

    #[Rest\Post('conversations')]
    #[Rest\RequestParam(name: 'members', map: true, requirements: '\d+', description: 'User ids of people to include in the conversation.')]
    public function createConversation(ParamFetcher $paramFetcher): Response
    {
        $this->assertLoggedIn();

        $members = $paramFetcher->get('members');
        $members[] = $this->session->id();
        $members = array_unique($members);
        if (!$this->foodsaverGateway->foodsaversExist($members)) {
            throw new NotFoundHttpException('At least one of the members could not be found');
        }

        $conversationId = $this->messageGateway->getOrCreateConversation($members);

        $conversationData = $this->getConversationData($conversationId, 20);

        return $this->respondOK($conversationData);
    }

    #[Rest\Get('conversations')]
    #[Rest\QueryParam(name: 'limit', requirements: '\d+', default: '20', description: 'How many conversations to return.')]
    #[Rest\QueryParam(name: 'offset', requirements: '\d+', default: '0', description: 'Offset returned conversations.')]
    public function getConversations(ParamFetcher $paramFetcher): Response
    {
        $this->assertLoggedIn();

        $limit = $paramFetcher->get('limit');
        $offset = $paramFetcher->get('offset');

        $data = $this->messageTransactions->listConversationsWithProfilesForUser($this->session->id(), $limit, $offset);

        return $this->respondOK([
            'conversations' => array_values($data['conversations']),
            'profiles' => array_values($data['profiles'])
        ]);
    }

    #[Rest\Post('conversations/{conversationId}/messages', requirements: ['conversationId' => '\d+'])]
    #[Rest\RequestParam(name: 'body', nullable: false)]
    public function sendMessage(int $conversationId, ParamFetcher $paramFetcher): Response
    {
        $this->assertLoggedIn();
        if (!$this->messageGateway->mayConversation($this->session->id(), $conversationId)) {
            throw new AccessDeniedHttpException();
        }
        $body = $paramFetcher->get('body');
        $message = $this->messageTransactions->sendMessage($conversationId, $this->session->id(), $body);

        return $this->respondOK(['message' => $message]);
    }

    #[Rest\Patch('conversations/{conversationId}', requirements: ['conversationId' => '\d+'])]
    #[Rest\RequestParam(name: 'name', nullable: true, default: null)]
    public function patchConversation(int $conversationId, ParamFetcher $paramFetcher): Response
    {
        $this->assertLoggedIn();
        if (!$this->messageGateway->mayConversation($this->session->id(), $conversationId)) {
            throw new UnauthorizedHttpException('');
        }
        if ($this->messageGateway->isConversationLocked($conversationId)) {
            throw new AccessDeniedHttpException();
        }

        if ($name = $paramFetcher->get('name')) {
            /* a name needs to have a non-zero length */
            $this->messageGateway->renameConversation($conversationId, $name);
        }

        return $this->respondOK();
    }

    #[Rest\Delete('conversations/{conversationId}/members/{userId}', requirements: ['conversationId' => '\d+', 'userId' => '\d+'])]
    public function removeMemberFromConversation(int $conversationId, int $userId): Response
    {
        /* disable functionality for now */
        /* only allow users to remove themselves from conversations */
        throw new AccessDeniedHttpException();
        /*
        if (!$this->session->mayRole() || $userId !== $this->session->id()) {
            throw new AccessDeniedHttpException();
        }
        if (!$this->messageTransactions->deleteUserFromConversation($conversationId, $userId)) {
            throw new BadRequestHttpException();
        }

        return $this->handleView($this->view([], 200));
        */
    }

    #[Rest\Get('user/{userId}/conversation', requirements: ['userId' => '\d+'])]
    public function getUserConversation(int $userId): Response
    {
        $this->assertLoggedIn();
        if ($userId == $this->session->id()) {
            throw new AccessDeniedHttpException();
        }

        if (!$this->foodsaverGateway->foodsaverExists($userId)) {
            throw new NotFoundHttpException();
        }

        $conversationId = $this->messageGateway->getOrCreateConversation([$this->session->id(), $userId]);

        return $this->respondOK(['id' => $conversationId]);
    }
}
