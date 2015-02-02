# Sentinel Change Log

This project follows [Semantic Versioning](CONTRIBUTING.md).

## Proposals

We do not give estimated times for completion on `Accepted` Proposals.

- [Accepted](https://github.com/cartalyst/sentinel/labels/Accepted)
- [Rejected](https://github.com/cartalyst/sentinel/labels/Rejected)

---

#### v1.0.8 - 2015-01-23

`ADDED`

- Mysql database schema.

`FIXED`

- A bug on the `findByCredentials` method that caused the first user to be returned when an empty array is passed.

#### v1.0.7 - 2014-10-21

`ADDED`

- `$hidden` property to the user model with the password field being hidden by default.

#### v1.0.6 - 2014-09-24

`FIXED`

- Wrap garbageCollect into a try catch block to prevent an exception from being thrown if the database is not setup.

#### v1.0.5 - 2014-09-16

`FIXED`

- A minor issue when deleting a user, the method wasn't returning the expected boolean only null.

#### v1.0.4 - 2014-09-15

`REVISED`

- Improved the requirements to allow the installation on Laravel 5.0.

#### v1.0.3 - 2014-09-13

`REVISED`

- Updated the updatePermission method signature on the PermissibleInterface due to a PHP bug on older versions.

#### v1.0.2 - 2014-09-10

`ADDED`

- An IoC Container alias for the Sentinel class.

`FIXED`

- Fixed some doc blocks typos

`REVISED`

- Loosened the requirements on the composer.json

#### v1.0.1 - 2014-08-07

`FIXED`

- A bug where user model overriding was ignored.

#### v1.0.0 - 2014-08-05

`ADDED`

- Authentication.
- Authorization.
- Registration.
- Driver based permission system.
- Flexible activation scenarios.
- Reminders. (password reset)
- Inter-account throttling with DDoS protection.
- Roles and role permissions.
- Remember me.
- Interface driven. (your own implementations at will)
