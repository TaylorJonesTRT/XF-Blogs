# Blog Node Type

## Context

The Blogs addon currently has no integration with XenForo's node tree. Blogs exist as standalone entities at `/blogs/` with no way for admins to place a "Blog" section in the forum node hierarchy alongside Forums, Categories, and Pages. This plan adds a bare-bones "Blog" node type so admins can create Blog nodes in the node tree, each housing a filtered set of blogs assigned to it. Existing blogs remain unassigned (`node_id = 0`) and accessible via the current `/blogs/` route.

## Decisions

- **Display name**: "Blog" (shown in node type selector)
- **Blog assignment**: Blogs get a `node_id` column to associate them with a specific Blog node
- **URL routing**: Blogs keep their existing `/blogs/blog/123` URLs; the Blog node view just filters which blogs it displays
- **Migration**: Existing blogs left unassigned (`node_id = 0`), no data migration

## New Files

### 1. `Entity/BlogForum.php` — Node data entity

Extends `XF\Entity\AbstractNode`. Follows the Category pattern (simplest node type).

- Table: `xf_taylorj_blogs_blog_forum`
- PK: `node_id`
- Single column: `node_id` (UINT, required)
- `getNodeTemplateRenderer($depth)` returns `taylorj_blogs_node_list_blog_forum` template
- `getNodeListExtras()` returns blog count for this node
- Relation to `Blogs` (TO_MANY on `TaylorJ\Blogs:Blog` via `node_id`)
- Call `static::addDefaultNodeElements($structure)` in `getStructure()`

**Reference**: `src/XF/Entity/Category.php`

### 2. `NodeType/BlogForumHandler.php` — Node type handler

Extends `XF\NodeType\AbstractHandler`. No-op `setupApiTypeDataEdit()` (no extra type-specific fields for now).

**Reference**: `src/XF/NodeType/CategoryHandler.php`

### 3. `Admin/Controller/BlogForumController.php` — Admin CRUD

Extends `XF\Admin\Controller\AbstractNode` which provides `actionAdd`, `actionEdit`, `actionSave`, `actionDelete`.

```
getNodeTypeId()      -> 'taylorjBlogsForum'
getDataParamName()   -> 'blogForum'
getTemplatePrefix()  -> 'taylorj_blogs_blog_forum'
getViewClassPrefix() -> 'TaylorJ\Blogs:BlogForum'
```

**Reference**: `src/XF/Admin/Controller/CategoryController.php`

### 4. `Pub/Controller/BlogForumController.php` — Public view

Extends `XF\Pub\Controller\AbstractController`.

- `actionIndex(ParameterBag $params)` — Assert viewable, fetch blogs where `node_id` matches this node, fetch child nodes, return view
- `assertViewableBlogForum($nodeIdOrName)` — Finder with Node permissions, `canView()` check, `NodePlugin::applyNodeContext()`
- `static getActivityDetails(array $activities)` — Session activity via `NodePlugin::getNodeActivityDetails()`

**Reference**: `src/XF/Pub/Controller/CategoryController.php`

### 5. `_output/templates/admin/taylorj_blogs_blog_forum_edit.html`

Uses `node_edit_macros` for title, description, node_name, position, navigation, style. Follows `category_edit.html` pattern.

### 6. `_output/templates/admin/taylorj_blogs_blog_forum_delete.html`

Standard node delete confirmation (child nodes action selector).

### 7. `_output/templates/public/taylorj_blogs_blog_forum_view.html`

Public view showing:
- Node title + description
- Breadcrumbs from node
- Child nodes (if any) via `forum_list::node_list` macro
- Blog listing filtered to this node (reuse markup patterns from `taylorj_blogs_index.html`)
- Pagination

### 8. `_output/templates/public/taylorj_blogs_node_list_blog_forum.html`

Node list rendering with `depth1`, `depth2`, `depthN` macros. Shows node title, description, blog count. Links to `blog-forums` route.

**Reference**: `src/addons/XF/_output/templates/public/node_list_category.html`

### 9. Route definitions

**`_output/routes/admin_blog-forums_.json`**
- route_prefix: `blog-forums`
- controller: `TaylorJ\Blogs:BlogForum`
- format: `:int<node_id,title>/`
- context: `nodes`

**`_output/routes/public_blog-forums_.json`**
- route_prefix: `blog-forums`
- controller: `TaylorJ\Blogs:BlogForum`
- format: `:str_int<node_name,node_id,title>/:page`
- context: `taylorjBlogs`

### 10. Phrases (create via CLI or admin panel)

| Phrase | Text |
|---|---|
| `node_type.taylorjBlogsForum` | `Blog` |
| `taylorj_blogs_add_blog_forum` | `Add blog` |
| `taylorj_blogs_edit_blog_forum` | `Edit blog:` |
| `taylorj_blogs_viewing_blog_forum` | `Viewing blog` |
| `taylorj_blogs_requested_blog_forum_not_found` | `The requested blog could not be found.` |

## Modified Files

### 1. `Setup.php` — Schema + node type registration

**New install step** (`installStep8`):
- Create `xf_taylorj_blogs_blog_forum` table (single `node_id` INT column, PK)
- Insert into `xf_node_type`:
  - `node_type_id`: `taylorjBlogsForum`
  - `entity_identifier`: `TaylorJ\Blogs:BlogForum`
  - `permission_group_id`: `taylorjBlogs`
  - `admin_route`: `blog-forums`
  - `public_route`: `blog-forums`
  - `handler_class`: `TaylorJ\Blogs:BlogForum`
- Rebuild node type cache

**New upgrade step** (for next version, e.g., `upgrade1080070Step1`):
- Same as install step (create table + insert node type + rebuild cache)

**New upgrade step** (`upgrade1080070Step2`):
- Alter `xf_taylorj_blogs_blog` to add `node_id INT DEFAULT 0` column with index

**Update uninstall** (add `uninstallStep5`):
- Drop `xf_taylorj_blogs_blog_forum` table
- Delete from `xf_node_type` where `node_type_id = 'taylorjBlogsForum'`
- Drop `node_id` column from `xf_taylorj_blogs_blog`
- Rebuild node type cache

**Add helper**:
```php
protected function rebuildNodeTypeCache()
{
    $this->app->repository(\XF\Repository\NodeTypeRepository::class)->rebuildNodeTypeCache();
}
```

### 2. `Entity/Blog.php` — Add node_id column + relation

- Add `node_id` column to `getStructure()`: `['type' => self::UINT, 'default' => 0]`
- Add `BlogForum` relation: TO_ONE on `TaylorJ\Blogs:BlogForum` via `node_id`
- No behavior changes needed for bare-bones

### 3. `Finder/Blog.php` — Add node filtering method

- Add `inNode($nodeId)` method: `->where('node_id', $nodeId)`

### 4. `addon.json` — Version bump

- Bump to next version (e.g., `1.8.0` / `1080070`)

## Verification

1. **Install/upgrade**: Run `xf-addon:upgrade TaylorJ/Blogs` to execute the new upgrade step
2. **Import**: Run `xf-dev:import --addon=TaylorJ/Blogs` to load routes, phrases, templates
3. **Admin**: Go to Admin > Nodes > Add node — verify "Blog" appears as a node type option
4. **Create node**: Create a Blog node, verify it saves and appears in the node tree
5. **Node list**: Verify the Blog node renders correctly in the public forum list
6. **Public view**: Click the Blog node, verify it shows the blog listing (empty for a new node since no blogs are assigned)
7. **Blog assignment**: Edit a blog to assign it to the new node (or directly update `node_id` in DB for testing), verify it appears in the node's public view
8. **Unassigned blogs**: Verify the existing `/blogs/` route still shows all blogs regardless of node assignment
9. **Delete node**: Delete the Blog node from admin, verify cleanup works
