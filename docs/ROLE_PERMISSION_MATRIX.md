# Role Permission Matrix

This matrix describes the intended target-state permissions. Final implementation may refine details, but must not broaden access without documentation.

## Roles

- `anonymous`
- `member`
- `web_aktuar`
- `member_admin`
- `president`
- `system_admin`

## Matrix

| Capability | anonymous | member | web_aktuar | member_admin | president | system_admin |
| --- | --- | --- | --- | --- | --- | --- |
| View public pages | yes | yes | yes | yes | yes | yes |
| View own account | no | yes | yes | yes | yes | yes |
| Edit own basic profile | no | yes | yes | yes | yes | yes |
| View internal dashboard | no | yes, limited | yes | yes | yes | yes |
| View internal events | no | if permitted | if permitted | if permitted | yes | yes |
| Download public documents | yes | yes | yes | yes | yes | yes |
| Download role-restricted private documents | no | if permitted | if permitted | if permitted | yes | yes |
| Upload public media | no | no | yes | no | optional | yes |
| Upload private media/documents | no | no | limited | yes | yes | yes |
| Create and edit drafts | no | no | yes | no | optional | yes |
| Publish or unpublish public content | no | no | yes, if granted | no | yes | yes |
| Manage navigation | no | no | yes | no | optional | yes |
| Manage events and news | no | no | yes | no | optional | yes |
| Invite new users | no | no | no | yes | yes | yes |
| Manage member profiles | no | no | no | yes | yes | yes |
| Change roles/groups | no | no | no | no | limited | yes |
| View audit logs | no | no | limited if granted | limited if granted | yes | yes |
| Manage system settings | no | no | no | no | no | yes |

## Notes

- `web_aktuar` is a content/editorial role, not an infrastructure administrator.
- `member_admin` is responsible for people and membership workflows, not unrestricted site design.
- `president` is included as a policy-level role for elevated oversight, not as a technical superuser by default.
- `system_admin` has technical control and must use stronger security controls, especially 2FA.

## Sensitive actions that always require server-side checks

- private document download
- private media access
- member data visibility
- invitation creation and redemption
- publishing and unpublishing
- role changes
- audit-log access
