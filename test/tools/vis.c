#include <stdio.h>
#include <string.h>
#include <stdlib.h>
#include <bsd/vis.h>

/**
 * Requires the libbsd headers.
 * Encodes a string with strvis to stdout and exits.
 *
 * gcc -lbsd -o vis vis.c
 */

int main(int argc, char *argv[])
{
    const char *input;
    char *encoded = NULL;

    if (argc != 2) {
        printf("usage: vis 'INPUT'\n");
        exit(EXIT_FAILURE);
    }

    input = argv[1];
    encoded = malloc(4 * strlen(input) + 1);

    strvis(encoded, input, VIS_WHITE);
    printf("%s", encoded);

    free(encoded);

    exit(EXIT_SUCCESS);
}
