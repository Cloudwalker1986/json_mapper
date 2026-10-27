PHP_ARG_ENABLE([json_mapper], [whether to enable json_mapper], [AS_HELP_STRING([--enable-json_mapper], [Enable json_mapper])], [no])
if test "$PHP_JSON_MAPPER" != "no"; then
  PHP_NEW_EXTENSION([json_mapper], [src/json_mapper.c src/json_mapper_attributes.c], [$ext_shared])
fi
