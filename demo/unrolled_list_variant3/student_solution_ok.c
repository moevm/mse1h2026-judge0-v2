
struct ListStruct *list_init() {
    struct ListStruct *list = malloc(sizeof(*list));
    list->head = NULL;
    return list;
}

void list_destroy(struct ListStruct *list) {
    struct ListNode *cur = list->head;
    while (cur) {
        struct ListNode *next = cur->next;
        free(cur);
        cur = next;
    }
    free(list);
}

bool list_is_empty(struct ListStruct *list) {
    return list->head == NULL;
}

int list_count(struct ListStruct *list) {
    int c = 0;
    struct ListNode *cur = list->head;
    while (cur) {
        c += cur->count;
        cur = cur->next;
    }
    return c;
}

struct ListNode *list_get(struct ListStruct *list, int index, int *index_in_block) {

    if (index < 0)
        return NULL;

    struct ListNode *cur = list->head;

    while (cur) {

        if (index < cur->count) {
            *index_in_block = index;
            return cur;
        }

        index -= cur->count;
        cur = cur->next;
    }

    return NULL;
}

void list_push_front(struct ListStruct *list, int data) {

    list_push(list, 0, data);
}

void list_push_back(struct ListStruct *list, int data) {

    list_push(list, list_count(list), data);
}

void list_push(struct ListStruct *list, int index, int data) {

    int n = list_count(list);

    if (index < 0)
        index = 0;
    if (index > n)
        index = n;

    int *arr = malloc((n + 1) * sizeof(int));
    int pos = 0;

    struct ListNode *cur = list->head;
    while (cur) {
        for (int i = 0; i < cur->count; i++)
            arr[pos++] = cur->data[i];
        cur = cur->next;
    }

    for (int i = n; i > index; i--)
        arr[i] = arr[i - 1];

    arr[index] = data;

    cur = list->head;
    while (cur) {
        struct ListNode *next = cur->next;
        free(cur);
        cur = next;
    }

    list->head = NULL;

    int i = 0;
    struct ListNode *prev = NULL;

    while (i < n + 1) {

        struct ListNode *node = malloc(sizeof(*node));
        node->count = 0;
        node->next = NULL;

        for (int j = 0; j < BLOCK_SIZE && i < n + 1; j++)
            node->data[node->count++] = arr[i++];

        if (!list->head)
            list->head = node;
        else
            prev->next = node;

        prev = node;
    }

    free(arr);
}

void list_pop(struct ListStruct *list, int index) {

    int n = list_count(list);

    if (index < 0 || index >= n)
        return;

    int *arr = malloc(n * sizeof(int));
    int pos = 0;

    struct ListNode *cur = list->head;
    while (cur) {
        for (int i = 0; i < cur->count; i++)
            arr[pos++] = cur->data[i];
        cur = cur->next;
    }

    for (int i = index; i < n - 1; i++)
        arr[i] = arr[i + 1];

    cur = list->head;
    while (cur) {
        struct ListNode *next = cur->next;
        free(cur);
        cur = next;
    }

    list->head = NULL;

    int i = 0;
    struct ListNode *prev = NULL;

    while (i < n - 1) {

        struct ListNode *node = malloc(sizeof(*node));
        node->count = 0;
        node->next = NULL;

        for (int j = 0; j < BLOCK_SIZE && i < n - 1; j++)
            node->data[node->count++] = arr[i++];

        if (!list->head)
            list->head = node;
        else
            prev->next = node;

        prev = node;
    }

    free(arr);
}

void list_sort(struct ListStruct *list) {

    int n = list_count(list);
    if (n == 0)
        return;

    int *arr = malloc(n * sizeof(int));
    int pos = 0;

    struct ListNode *cur = list->head;
    while (cur) {
        for (int i = 0; i < cur->count; i++)
            arr[pos++] = cur->data[i];
        cur = cur->next;
    }

    for (int i = 0; i < n; i++)
        for (int j = 0; j < n - 1; j++)
            if (arr[j] > arr[j + 1]) {
                int t = arr[j];
                arr[j] = arr[j + 1];
                arr[j + 1] = t;
            }

    cur = list->head;
    while (cur) {
        struct ListNode *next = cur->next;
        free(cur);
        cur = next;
    }

    list->head = NULL;

    int i = 0;
    struct ListNode *prev = NULL;

    while (i < n) {

        struct ListNode *node = malloc(sizeof(*node));
        node->count = 0;
        node->next = NULL;

        for (int j = 0; j < BLOCK_SIZE && i < n; j++)
            node->data[node->count++] = arr[i++];

        if (!list->head)
            list->head = node;
        else
            prev->next = node;

        prev = node;
    }

    free(arr);
}

void list_remove_duplicates(struct ListStruct *list) {

    int n = list_count(list);
    if (n == 0)
        return;

    int *arr = malloc(n * sizeof(int));
    int pos = 0;

    struct ListNode *cur = list->head;
    while (cur) {
        for (int i = 0; i < cur->count; i++)
            arr[pos++] = cur->data[i];
        cur = cur->next;
    }

    int *res = malloc(n * sizeof(int));
    int m = 0;

    for (int i = 0; i < n; i++) {

        bool found = false;

        for (int j = 0; j < m; j++)
            if (res[j] == arr[i])
                found = true;

        if (!found)
            res[m++] = arr[i];
    }

    cur = list->head;
    while (cur) {
        struct ListNode *next = cur->next;
        free(cur);
        cur = next;
    }

    list->head = NULL;

    int i = 0;
    struct ListNode *prev = NULL;

    while (i < m) {

        struct ListNode *node = malloc(sizeof(*node));
        node->count = 0;
        node->next = NULL;

        for (int j = 0; j < BLOCK_SIZE && i < m; j++)
            node->data[node->count++] = res[i++];

        if (!list->head)
            list->head = node;
        else
            prev->next = node;

        prev = node;
    }

    free(arr);
    free(res);
}

double list_median(struct ListStruct *list) {

    int n = list_count(list);
    if (n == 0)
        return 0;

    int *arr = malloc(n * sizeof(int));
    int pos = 0;

    struct ListNode *cur = list->head;
    while (cur) {
        for (int i = 0; i < cur->count; i++)
            arr[pos++] = cur->data[i];
        cur = cur->next;
    }

    for (int i = 0; i < n; i++)
        for (int j = 0; j < n - 1; j++)
            if (arr[j] > arr[j + 1]) {
                int t = arr[j];
                arr[j] = arr[j + 1];
                arr[j + 1] = t;
            }

    double result;

    if (n % 2)
        result = arr[n / 2];
    else
        result = (arr[n / 2 - 1] + arr[n / 2]) / 2.0;

    free(arr);
    return result;
}

void list_reverse_blocks(struct ListStruct *list) {

    struct ListNode *prev = NULL;
    struct ListNode *cur = list->head;

    while (cur) {
        struct ListNode *next = cur->next;
        cur->next = prev;
        prev = cur;
        cur = next;
    }

    list->head = prev;
}
