# Facebook Long-Lived Token Guide

Use this when `FB_PAGE_ACCESS_TOKEN` has expired and you need a fresh token for Facebook Page posting.

## Goal

Get a fresh **Page access token** for the `BSU Freedom Wall` Page, using a **long-lived user token** as the intermediate step.

The app should store the final **Page token** in:

```env
FB_PAGE_ACCESS_TOKEN=...
```

## Important

- Do **not** paste your App Secret into chat, screenshots, or public notes.
- If your App Secret was exposed, rotate it in Meta before continuing.
- The token used in `.env` is the **Page token**, not the user token.

## Step 1: Generate a User Token

In Graph API Explorer:

1. Select your Meta app
2. Generate a **user token**
3. Include these permissions:
   - `pages_show_list`
   - `pages_read_engagement`
   - `pages_manage_posts`

Keep that token. It is your **short-lived user token**.

## Step 2: Exchange It for a Long-Lived User Token

Open this URL in the browser or run it with `curl`:

```text
https://graph.facebook.com/v25.0/oauth/access_token?grant_type=fb_exchange_token&client_id=YOUR_APP_ID&client_secret=YOUR_APP_SECRET&fb_exchange_token=YOUR_SHORT_LIVED_USER_TOKEN
```

Example response:

```json
{
  "access_token": "EAAv...",
  "token_type": "bearer",
  "expires_in": 5183944
}
```

The returned `access_token` is your **long-lived user token**.

## Step 3: Use the Long-Lived User Token to Get the Page Token

Call:

```text
/me/accounts?fields=id,name,access_token&access_token=YOUR_LONG_LIVED_USER_TOKEN
```

In Graph API Explorer, paste that into the request box and submit it.

The response should include something like:

```json
{
  "data": [
    {
      "id": "1174457465757788",
      "name": "BSU Freedom Wall",
      "access_token": "EAAv..."
    }
  ]
}
```

Copy the `access_token` for `BSU Freedom Wall`.

That is your **Page token**.

## Step 4: Update the App

Put the Page token into `.env`:

```env
FB_PAGE_ACCESS_TOKEN=YOUR_PAGE_TOKEN
```

Then clear Laravel caches:

```bash
php artisan optimize:clear
```

## Step 5: Retry Failed Facebook Posts

After updating the token and clearing cache, retry the failed Facebook post from the admin dashboard.

## Quick Token Flow

```text
short-lived user token
-> long-lived user token
-> /me/accounts
-> page token
-> FB_PAGE_ACCESS_TOKEN in .env
```

## Common Mistakes

### Using the Page ID as `client_id`

Wrong:

```text
client_id=1174457465757788
```

Right:

```text
client_id=YOUR_APP_ID
```

### Putting the token inside `fields`

Wrong:

```text
/me/accounts?fields=id,name,EAAv...
```

Right:

```text
/me/accounts?fields=id,name,access_token&access_token=EAAv...
```

### Putting the user token in `.env`

Wrong:

```env
FB_PAGE_ACCESS_TOKEN=USER_TOKEN
```

Right:

```env
FB_PAGE_ACCESS_TOKEN=PAGE_TOKEN
```

## Notes

- Long-lived **user** tokens usually last about **60 days**.
- The **Page** token derived from it may last longer, but do not assume it is permanent.
- For the most stable production setup, move to a **Business System User token** later.


https://developers.facebook.com/tools/explorer