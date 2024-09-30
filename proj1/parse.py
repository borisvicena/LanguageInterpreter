import re
import sys
import xml.etree.ElementTree as ET
from xml.dom import minidom

# Constants for error codes
ERR_INVALID_HEADER = 21
ERR_INVALID_OPCODE = 22
ERR_LEXICAL_SYNTAX = 23
ERR_ARGS = 10
NO_ERR = 0

class Token:
    """
    Token class
    """
    def __init__(self, data, type, line_number):
        self.data = data
        self.type = type
        self.line_number = line_number

    def __str__(self):
        return f"Type: {self.type} - ({self.data}, line {self.line_number})"

class LexerFSM:
    """
    LexerFSM class
    """
    # List of valid instructions
    instructions = [
        'MOVE', 'CREATEFRAME', 'PUSHFRAME', 'POPFRAME', 'DEFVAR',
        'CALL', 'RETURN', 'PUSHS', 'POPS', 'ADD', 'SUB', 'MUL', 'IDIV',
        'LT', 'GT', 'EQ', 'AND', 'OR', 'NOT', 'INT2CHAR', 'STRI2INT',
        'READ', 'WRITE', 'CONCAT', 'STRLEN', 'GETCHAR', 'SETCHAR', 'TYPE',
        'LABEL', 'JUMP', 'JUMPIFEQ', 'JUMPIFNEQ', 'EXIT', 'DPRINT', 'BREAK',
        "INT2FLOAT", "FLOAT2INT", "DIV", "CLEARS", "ADDS", "SUBS", "MULS",
        "IDIVS", "LTS", "GTS", "EQS", "ANDS", "ORS", "NOTS", "INT2CHARS",
        "STRI2INTS", "JUMPIFEQS", "JUMPIFNEQS"
    ]

    def __init__(self, code):
        # Constructor
        self.code = code

    def remove_comments(self):
        # Remove comments from code
        return re.sub(r'#.*?$', '', self.code, flags=re.MULTILINE)
    
    def tokenize(self):
        # Tokenize code
        code = self.remove_comments()
        lines = code.splitlines()
        tokens = []
        for line_number, line in enumerate(lines, start=1):
            line_tokens = self.tokenize_line(line, line_number)
            tokens.extend(line_tokens)
        return tokens

    def tokenize_line(self, line, line_number):
        # Tokenize each line of the code
        line = line.strip()
        tokens_data = re.findall(r"\S+", line)
        tokens = []
        instruction_found = 0
        for token_data in tokens_data:
            token = self.classify_token(token_data, line_number)
            tokens.append(token)

            if token.type in self.instructions:
                instruction_found += 1

        # Check for invalid opcode
        if tokens_data and line_number > 1:
            f_ins = tokens[0].type
            f_val = tokens[0].data
            if any(instruction in f_val for instruction in self.instructions) and f_ins == "label":
                print(f"Error: Invalid OPCODE '{f_val}'")
                exit_with(ERR_INVALID_OPCODE, f"Exiting with code {ERR_INVALID_OPCODE}")

        # Check for multiple instructions on one line
        if instruction_found > 1:
            print("Error: Multiple instructions on one line")
            exit_with(ERR_LEXICAL_SYNTAX, f"Exiting with code {ERR_LEXICAL_SYNTAX}")

        return tokens

    def classify_token(self, data, line_number):
        # Match and classify a token based on its data
        if re.match(r"\.IPPcode24(?:\s|$)", data):
            return Token(data, "header", line_number)
        elif re.match(r"(GF|LF|TF)@[a-zA-Z_\-$&%*!?][0-9a-zA-Z_\-$&%*!?]*", data):
            return Token(data, "var", line_number)
        elif re.match(r"int@[+-]?[0-9]+", data):
            return Token(data.split("@")[1], "int", line_number)
        elif re.match(r"float@([+-]?(?:0[xX])?[0-9a-fA-F]+(?:\.[0-9a-fA-F]+)?(?:[pP][+-]?\d+)?)", data):
            return Token(data.split("@")[1], "float", line_number)
        elif re.match(r"bool@(true|false)", data):
            return Token(data.split("@")[1], "bool", line_number)
        elif re.match(r"string@.*", data):
            string_content = data.split("@")[1]
            string_validation = self.validate_escape_sequences(string_content)
            if string_validation:
                return Token(data.split("@")[1], "string", line_number)
            else:
                print("Error: Incorrect escape sequence in the string")
                exit_with(ERR_LEXICAL_SYNTAX, f"Exiting with coude {ERR_LEXICAL_SYNTAX}")
        elif re.match(r"nil@nil\b", data):
            return Token(data.split("@")[1], "nil", line_number)
        elif data in self.instructions:
            return Token(data, data.upper(), line_number)
        elif re.match(r"^(?![@\\])[a-zA-Z0-9_\-$&%*!?][a-zA-Z0-9_\-$&%*!?]*", data):
            if data.upper() in self.instructions and data.upper() != "LABEL":
                return Token(data, data.upper(), line_number)
            else:
                return Token(data, "label", line_number)
        else:
            return Token(data, "UNKNOWN", line_number)

    def validate_escape_sequences(self, string_content):
        # Validate escape sequences in string
        i = 0
        clean_string = True
        while i < len(string_content):
            if string_content[i] == "\\" and not (i + 3 < len(string_content) and string_content[i + 1:i + 4].isdigit()):
                clean_string = False
                break
                i += 3
            elif string_content[i:i + 7] == "string@":
                while i < len(string_content) and string_content[i] != "\\":
                    i += 1
                continue
            i += 1

        return clean_string

class Parser:
    """
    Parser class
    """
    INSTRUCTION_ARGUMENTS_TYPES = {
        "MOVE": ["var", "symb"],
        "CREATEFRAME": [],
        "PUSHFRAME": [],
        "POPFRAME": [],
        "DEFVAR": ["var"],
        "CALL": ["label"],
        "RETURN": [],
        "PUSHS": ["symb"],
        "POPS": ["var"],
        "ADD": ["var", "symb", "symb"],
        "SUB": ["var", "symb", "symb"],
        "MUL": ["var", "symb", "symb"],
        "IDIV": ["var", "symb", "symb"],
        "LT": ["var", "symb", "symb"],
        "GT": ["var", "symb", "symb"],
        "EQ": ["var", "symb", "symb"],
        "AND": ["var", "symb", "symb"],
        "OR": ["var", "symb", "symb"],
        "NOT": ["var", "symb"],
        "INT2CHAR": ["var", "symb"],
        "STRI2INT": ["var", "symb", "symb"],
        "INT2FLOAT": ["var", "symb"],
        "FLOAT2INT": ["var", "symb", "symb"],
        "DIV": ["var", "symb", "symb"],
        "READ": ["var", "type"],
        "WRITE": ["symb"],
        "CONCAT": ["var", "symb", "symb"],
        "STRLEN": ["var", "symb"],
        "GETCHAR": ["var", "symb", "symb"],
        "SETCHAR": ["var", "symb", "symb"],
        "TYPE": ["var", "symb"],
        "LABEL": ["label"],
        "JUMP": ["label"],
        "JUMPIFEQ": ["label", "symb", "symb"],
        "JUMPIFNEQ": ["label", "symb", "symb"],
        "EXIT": ["symb"],
        "DPRINT": ["symb"],
        "BREAK": [],
        "CLEARS": [],
        "ADDS": [],
        "SUBS": [],
        "MULS": [],
        "IDIVS": [],
        "LTS": [],
        "GTS": [],
        "EQS": [],
        "ANDS": [],
        "ORS": [],
        "NOTS": [],
        "INT2CHARS": [],
        "STRI2INTS": [],
        "JUMPIFEQS": [],
        "JUMPIFNEQS": []
    }

    def __init__(self, tokens):
        self.tokens = tokens
        self.program = {"instructions": []}
    
    def parse(self):
        # Check for missing or invalid header
        if not self.tokens or self.tokens[0].type != "header":
            print("Error: Missing or invalid header")
            exit_with(ERR_INVALID_HEADER, f"Exiting with code {ERR_INVALID_HEADER}")
        self.tokens.pop(0)

        # Parse tokens
        while self.tokens:
            self.parse_instruction()

    def parse_instruction(self):
        # Check for when an instruction is expected
        if not self.tokens:
            print(f"Error: No more tokens but expected an instruction")
            exit_with(ERR_LEXICAL_SYNTAX, f"Exiting with code {ERR_LEXICAL_SYNTAX}")
        token = self.tokens.pop(0)

        # Check for unknown or invalid instructions
        if token.type.upper() == "UNKNOWN" or token.type.upper() not in self.INSTRUCTION_ARGUMENTS_TYPES:
            print(f"Error: Invalid instruction {token.data}")
            exit_with(ERR_LEXICAL_SYNTAX, f"Exiting with code {ERR_LEXICAL_SYNTAX}")

        instruction_format = self.INSTRUCTION_ARGUMENTS_TYPES[token.type.upper()]
        args = []

        # Iterate over expected argument types for instructions
        for i, expected_type in enumerate(instruction_format):
            if not self.tokens:
                print(f"Error: Expected more arguments for {token.type}")
                exit_with(ERR_LEXICAL_SYNTAX, f"Exiting with code {ERR_LEXICAL_SYNTAX}")
            arg_token = self.tokens.pop(0)
            arg_type = self.arg_type_convert(arg_token.type)

            if arg_token.type == "label" and (arg_token.data == "int" or arg_token.data == "string" or arg_token.data == "bool"):
                arg_type = "type"

            # Check if READ instruction arguments are valid
            if (token.type == "READ") and (i != 0 and arg_type == "var"):
                print(f"Error: Invalid argument type for {token.type}: '{arg_token.type}' -> expected '{expected_type}'")
                exit_with(ERR_LEXICAL_SYNTAX, f"Exiting with code {ERR_LEXICAL_SYNTAX}")
            # Handle 'var' type when is not on the first position
            elif arg_type == "var" and i != 0:
                args.append({"type": arg_token.type, "value": arg_token.data})
            # Handle specific arguments types for instructions
            elif (token.type == "DPRINT" or token.type == "EXIT" or token.type == "PUSHS" or token.type == "WRITE") and (arg_type == "symb" or arg_type == "var"):
                args.append({"type": arg_token.type, "value": arg_token.data})
            # Check if argument type matches expected type
            elif arg_type not in expected_type:
                print(f"Error: Invalid argument on line {token.line_number} type for {token.type}: '{arg_token.type}' -> expected '{expected_type}'")
                exit_with(ERR_LEXICAL_SYNTAX, f"Exiting with code {ERR_LEXICAL_SYNTAX}")
            else:
                args.append({"type": arg_token.type, "value": arg_token.data})
        self.program["instructions"].append({"opcode": token.type.upper(), "args": args})

    def arg_type_convert(self, type):
        # Convert arguments types
        if type == "int" or type == "bool" or type == "string" or type == "nil" or type == "float":
            return "symb"
        else:
            return type

class XMLGenerator:
    """
    XMLGenerator class
    """
    def __init__(self, program):
        self.program = program

    def generate_xml(self):
        # Generate XML from instructions
        root = ET.Element("program")
        root.set("language", "IPPcode24")

        # Iterate over instructions
        for order, instruction in enumerate(self.program["instructions"], start=1):
            instr_elem = ET.SubElement(root, "instruction")
            instr_elem.set("order", str(order))
            instr_elem.set("opcode", instruction["opcode"])
            # Iterate over arguments in each instruction
            for i, arg in enumerate(instruction["args"], start=1):
                arg_elem = ET.SubElement(instr_elem, f"arg{i}")
                # Handle special labels
                if arg["type"] == "label" and (arg["value"] == "int" or arg["value"] == "string" or arg["value"] == "bool"):
                    arg["type"] = "type"
                arg_elem.set("type", arg["type"])
                arg_elem.text = arg["value"]

        return ET.tostring(root, encoding='utf-8')

    def format_xml(self, xml_string):
        # Format better XML
        xml_s = minidom.parseString(xml_string)
        return xml_s.toprettyxml(indent=" ", newl="\n", encoding="UTF-8")

def print_help():
    # Print a help message
    print("Usage: parse.py [--help]")

def exit_with(error_code, message):
    # Print error message to stderr and exit with given error code
    print(message, file=sys.stderr)
    sys.exit(error_code)

def main():
    # Check if there are more arguments after --help
    if len(sys.argv) > 2 and sys.argv[1] == "--help":
        print("Error: More arguments than expected")
        exit_with(ERR_ARGS, f"Exiting with code {ERR_ARGS}")

    # Handle --help parameter
    if len(sys.argv) > 1 and sys.argv[1] == "--help":
        print_help()
        exit(NO_ERR)
    else:
        code = sys.stdin.read()
        lexer = LexerFSM(code)
        tokens = lexer.tokenize()

        ####### DEBUG #######
        # for token in tokens[:20]:
        #     print(token)
        ####### DEBUG #######

        parser = Parser(tokens)
        parser.parse()

        xml_generator = XMLGenerator(parser.program)
        xml_output = xml_generator.generate_xml()
        xml_final = xml_generator.format_xml(xml_output)
        print(xml_final.decode("utf-8"))
        exit (NO_ERR)

if __name__ == "__main__":
    main()