#include <stdio.h>
#include <stdlib.h>
#include <stdbool.h>
#include <string.h>

#define BLOCK_SIZE 4

struct ListNode{
    int data[BLOCK_SIZE];
    int count;
    struct ListNode *next;
};

struct ListStruct{
    struct ListNode *head;
};

struct ListStruct *list_init();
void list_destroy(struct ListStruct *list);
void list_push_front(struct ListStruct *list,int data);
void list_push_back(struct ListStruct *list,int data);
void list_push(struct ListStruct *list,int index,int data);
void list_pop(struct ListStruct *list,int index);
int list_count(struct ListStruct *list);
bool list_is_empty(struct ListStruct *list);
struct ListNode *list_get(struct ListStruct *list,int index,int *index_in_block);
void list_sort(struct ListStruct *list);
void list_remove_duplicates(struct ListStruct *list);
double list_median(struct ListStruct *list);
void list_reverse_blocks(struct ListStruct *list);

int main(){

    struct ListStruct *list=list_init();
    char cmd[100];
    int a,b;

    while(1){

        scanf("%99s",cmd);

        if(!strcmp(cmd,"end")) break;

        else if(!strcmp(cmd,"pushHead")){
            scanf("%d",&a);
            list_push_front(list,a);
        }

        else if(!strcmp(cmd,"pushTail")){
            scanf("%d",&a);
            list_push_back(list,a);
        }

        else if(!strcmp(cmd,"push")){
            scanf("%d %d",&a,&b);
            list_push(list,a,b);
        }

        else if(!strcmp(cmd,"pop")){
            scanf("%d",&a);
            list_pop(list,a);
        }

        else if(!strcmp(cmd,"count"))
            printf("%d\n",list_count(list));

        else if(!strcmp(cmd,"isEmpty"))
            printf("%d\n",list_is_empty(list));

        else if(!strcmp(cmd,"sort"))
            list_sort(list);

        else if(!strcmp(cmd,"removeDuplicates"))
            list_remove_duplicates(list);

        else if(!strcmp(cmd,"median"))
            printf("%.1lf\n",list_median(list));

        else if(!strcmp(cmd,"reverseBlocks"))
            list_reverse_blocks(list);
    }

    printf("List:\n");

    while(!list_is_empty(list)) {
    
        int pos;
        struct ListNode *b = list_get(list, 0, &pos);
    
        printf("%d\n", b->data[pos]);
    
        list_pop(list, 0);
    }


    list_destroy(list);
}
