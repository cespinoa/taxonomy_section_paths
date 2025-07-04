## INTRODUCTION

The Taxonomy Section Paths module is a DESCRIBE_THE_MODULE_HERE.

The primary use case for this module is:

- Use case #1
- Use case #2
- Use case #3

## REQUIREMENTS

DESCRIBE_MODULE_DEPENDENCIES_HERE

## INSTALLATION

Install as you would normally install a contributed Drupal module.
See: https://www.drupal.org/node/895232 for further information.

## CONFIGURATION
- Configuration step #1
- Configuration step #2
- Configuration step #3

## MAINTAINERS

Current maintainers for Drupal 10:

- FIRST_NAME LAST_NAME (NICKNAME) - https://www.drupal.org/u/NICKNAME


📣 Nota importante sobre actualizaciones directas de términos
Advertencia: Si modificas o eliminas términos de taxonomía directamente (por ejemplo, vía Drush PHPEval, update queries o cualquier otra forma de acceso directo a la base de datos), esos cambios no dispararán automáticamente la regeneración de alias de nodos relacionada. Para mantener la coherencia de rutas, deberás reconstruir manualmente todos los alias de la siguiente manera:

1. Usar el comando Drush integrado
bash
Copiar
Editar
# Regenerar todos los alias de términos y nodos según la configuración actual.
drush taxonomy-section-paths:regenerate-alias
2. Limpiar la caché de Drupal
bash
Copiar
Editar
# Reconstruye la caché y asegura que todos los alias estén al día.
drush cache:rebuild
3. (Opcional) Reconstruir índices de búsqueda y accesos
Si utilizas módulos de búsqueda o permisos basados en alias:

bash
Copiar
Editar
drush search-api:index
drush php:eval "\Drupal::service('node_access_rebuild')->rebuild();"
