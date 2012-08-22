OP(LOADK, CONSTANT, 1, 1)
OP(DUP,   NONE,     1, 2)
OP(SWAP,  NONE,     2, 2)
OP(POP,   NONE,     1, 0)
OP(LOADV, VARIABLE, 1, 1)
OP(STOREV, VARIABLE, 1, 0)
OP(INDEX, NONE,     2, 1)
//OP(DISPLAY, NONE,   1, 0)
OP(YIELD, NONE, 1, 0)
OP(EACH,  NONE,     1, 1)
OP(FORK,  BRANCH,   0, 0)
OP(JUMP,  BRANCH,   0, 0)
OP(BACKTRACK, NONE, 0, 0)
OP(APPEND, NONE,    2, 1)
OP(INSERT, NONE,    4, 2)

OP(CALL_BUILTIN_1_1, CFUNC, 1, 1)
OP(CALL_BUILTIN_3_1, CFUNC, 3, 1)

OP(CALL_1_1, UFUNC, 1, 1)
OP(RET, NONE, 1, 1)

OP(CLOSURE_PARAM, CLOSURE_ACCESS, 0, 0)
OP(CLOSURE_REF, CLOSURE_ACCESS, 0, 0)
OP(CLOSURE_CREATE, CLOSURE_DEFINE, 0, 0)
