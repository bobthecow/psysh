#include <stdio.h>
#include <string.h>
#include <stdlib.h>
#include <bsd/vis.h>

/**
 * Requires the libbsd headers.
 * Decodes a string encoded by vis to stdout and exits.
 *
 * gcc -lbsd -o unvis unvis.c
 */

int main(int argc, char *argv[])
{
    const char *input;
    char *decoded = NULL;

    if (argc != 2) {
        printf("usage: unvis 'INPUT'\n");
        exit(EXIT_FAILURE);
    }

    input = argv[1];
    decoded = malloc(strlen(input) + 1);

    strunvis(decoded, input);
    printf("%s", decoded);

    free(decoded);

    exit(EXIT_SUCCESS);
}
