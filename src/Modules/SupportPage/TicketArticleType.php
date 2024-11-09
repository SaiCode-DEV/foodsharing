<?php

namespace Foodsharing\Modules\SupportPage;

/**
 * A ticket in Zammad can contain several articles. Each article has a type.
 */
enum TicketArticleType: string
{
    /**
     * An email sent from a customer to Zammad or by an agent to the customer.
     */
    case EMAIL = 'email'; // internal: 1
    /**
     * An article that was created by an agent in place of the customer.
     */
    case PHONE = 'phone'; // internal: 5
    /**
     * A note created by an agent.
     */
    case NOTE = 'note'; // internal: 10
    /**
     * A article that was created by a customer using the web interface.
     */
    case WEB = 'web'; // internal: 11
}
