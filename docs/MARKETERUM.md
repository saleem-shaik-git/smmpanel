# Marketerum API integration

Endpoint: `https://marketerum.com/api/v2`

Method: `POST`

Format: JSON response. Authentication is sent as the `key` form parameter. The key must remain server-side.

## Supported operations

- `action=services` — service catalogue
- `action=add` — create an order
- `action=status&order=ID` — one order status
- `action=status&orders=ID1,ID2` — up to 100 order statuses
- `action=refill&order=ID` — create one refill
- `action=refill&orders=ID1,ID2` — up to 100 refills
- `action=refill_status&refill=ID` — one refill status
- `action=refill_status&refills=ID1,ID2` — up to 100 refill statuses
- `action=cancel&orders=ID1,ID2` — cancel up to 100 orders
- `action=balance` — provider balance

## Add-order payloads

The service catalogue's `type` determines the extra fields required. The application stores the provider's complete service response in `services.provider_raw` so the UI can evolve without losing provider metadata.

Supported documented fields include:

- Default: `link`, `quantity`
- Runs/interval: `link`, `quantity`, `runs`, `interval`
- Keywords: `link`, `quantity`, `keywords`
- Custom comments: `link`, `comments`
- Usernames: `link`, `quantity`, `usernames`
- Usernames + hashtags: `link`, `quantity`, `usernames`, `hashtags`
- Hashtag scrape: `link`, `quantity`, `hashtag`
- Username scrape: `link`, `quantity`, `username`
- Media scrape: `link`, `quantity`, `media`
- Subscription: `username`, `min`, `max`, optional `posts`, `old_posts`, `delay`, `expiry`
- Comment owner: `link`, `comments`, `username`
- Poll: `link`, `quantity`, `answer_number`
- Comment replies: `link`, `quantity`, `username`, `comments`
- Groups: `link`, `quantity`, `groups`

The exact service type returned by Marketerum should drive which fields the customer sees. Do not expose arbitrary provider fields without validation.

## Statuses

Persist provider statuses such as `Pending`, `In progress`, `Completed`, `Partial`, and `Canceled` as normalized local status values while retaining the raw provider response.

## Important

Never commit the provider API key. Configure it through `.env` / hosting secrets. Rotate any API key that has been exposed in chat or source control before production use.
