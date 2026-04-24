# Remove orphaned "UserBlogs" navigation item

## Context
A duplicate "Blogs" navigation item linking to `/userblogs/` is appearing because the addon was previously named "UserBlogs." Orphaned output files from that old naming still exist in `_output/` and get imported into the database, creating a second top-level navigation entry.

## Changes

### 1. Delete orphaned navigation output file
- **Delete:** `_output/navigation/taylorjUserBlogs.json`

### 2. Delete orphaned route output files
- **Delete:** `_output/routes/public_userblogs_.json`
- **Delete:** `_output/routes/public_userblogs_blog.json`
- **Delete:** `_output/routes/public_userblogs_post.json`

### 3. Remove stale phrase from `_data/phrases.xml`
- **Remove** the `nav.taylorjUserBlogs` phrase entry

### 4. Re-import addon data
```bash
cd ~/Development/XenForo/2.3.9 && docker compose exec php php cmd.php xf-dev:import --addon=TaylorJ/Blogs
```

Then rebuild:
```bash
cd ~/Development/XenForo/2.3.9 && docker compose exec php php cmd.php xf-addon:rebuild TaylorJ/Blogs
```

## Verification
- After import/rebuild, check the site navigation — only one "Blogs" link should appear, pointing to `/blogs/`
- Confirm `/userblogs/` no longer resolves as a route
