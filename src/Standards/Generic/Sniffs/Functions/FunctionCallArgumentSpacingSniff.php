<?php
/**
 * Checks that calls to methods and functions are spaced correctly.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */

namespace PHP_CodeSniffer\Standards\Generic\Sniffs\Functions;

use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Util\Tokens;

class FunctionCallArgumentSpacingSniff implements Sniff
{


    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        return array(T_STRING);

    }//end register()


    /**
     * Processes this test, when one of its tokens is encountered.
     *
     * @param PHP_CodeSniffer_File $phpcsFile The file being scanned.
     * @param int                  $stackPtr  The position of the current token in the
     *                                        stack passed in $tokens.
     *
     * @return void
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();

        // Skip tokens that are the names of functions or classes
        // within their definitions. For example:
        // function myFunction...
        // "myFunction" is T_STRING but we should skip because it is not a
        // function or method *call*.
        $functionName    = $stackPtr;
        $ignoreTokens    = Tokens::$emptyTokens;
        $ignoreTokens[]  = T_BITWISE_AND;
        $functionKeyword = $phpcsFile->findPrevious($ignoreTokens, ($stackPtr - 1), null, true);
        if ($tokens[$functionKeyword]['code'] === T_FUNCTION || $tokens[$functionKeyword]['code'] === T_CLASS) {
            return;
        }

        // If the next non-whitespace token after the function or method call
        // is not an opening parenthesis then it cant really be a *call*.
        $openBracket = $phpcsFile->findNext(Tokens::$emptyTokens, ($functionName + 1), null, true);
        if ($tokens[$openBracket]['code'] !== T_OPEN_PARENTHESIS) {
            return;
        }

        if (isset($tokens[$openBracket]['parenthesis_closer']) === false) {
            return;
        }

        $closeBracket  = $tokens[$openBracket]['parenthesis_closer'];
        $nextSeparator = $openBracket;

        $find = array(
                 T_COMMA,
                 T_VARIABLE,
                 T_CLOSURE,
                 T_OPEN_SHORT_ARRAY,
                );

        while (($nextSeparator = $phpcsFile->findNext($find, ($nextSeparator + 1), $closeBracket)) !== false) {
            if ($tokens[$nextSeparator]['code'] === T_CLOSURE) {
                // Skip closures.
                $nextSeparator = $tokens[$nextSeparator]['scope_closer'];
                continue;
            } else if ($tokens[$nextSeparator]['code'] === T_OPEN_SHORT_ARRAY) {
                // Skips arrays using short notation.
                $nextSeparator = $tokens[$nextSeparator]['bracket_closer'];
                continue;
            }

            // Make sure the comma or variable belongs directly to this function call,
            // and is not inside a nested function call or array.
            $brackets    = $tokens[$nextSeparator]['nested_parenthesis'];
            $lastBracket = array_pop($brackets);
            if ($lastBracket !== $closeBracket) {
                continue;
            }

            if ($tokens[$nextSeparator]['code'] === T_COMMA) {
                if ($tokens[($nextSeparator - 1)]['code'] === T_WHITESPACE) {
                    $prev = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($nextSeparator - 2), null, true);
                    if (isset(Tokens::$heredocTokens[$tokens[$prev]['code']]) === false) {
                        $error = 'Space found before comma in function call';
                        $fix   = $phpcsFile->addFixableError($error, $nextSeparator, 'SpaceBeforeComma');
                        if ($fix === true) {
                            $phpcsFile->fixer->replaceToken(($nextSeparator - 1), '');
                        }
                    }
                }

                if ($tokens[($nextSeparator + 1)]['code'] !== T_WHITESPACE) {
                    $error = 'No space found after comma in function call';
                    $fix   = $phpcsFile->addFixableError($error, $nextSeparator, 'NoSpaceAfterComma');
                    if ($fix === true) {
                        $phpcsFile->fixer->addContent($nextSeparator, ' ');
                    }
                } else {
                    // If there is a newline in the space, then they must be formatting
                    // each argument on a newline, which is valid, so ignore it.
                    $next = $phpcsFile->findNext(Tokens::$emptyTokens, ($nextSeparator + 1), null, true);
                    if ($tokens[$next]['line'] === $tokens[$nextSeparator]['line']) {
                        $space = strlen($tokens[($nextSeparator + 1)]['content']);
                        if ($space > 1) {
                            $error = 'Expected 1 space after comma in function call; %s found';
                            $data  = array($space);
                            $fix   = $phpcsFile->addFixableError($error, $nextSeparator, 'TooMuchSpaceAfterComma', $data);
                            if ($fix === true) {
                                $phpcsFile->fixer->replaceToken(($nextSeparator + 1), ' ');
                            }
                        }
                    }
                }//end if
            } else {
                // Token is a variable.
                $nextToken = $phpcsFile->findNext(Tokens::$emptyTokens, ($nextSeparator + 1), $closeBracket, true);
                if ($nextToken !== false) {
                    if ($tokens[$nextToken]['code'] === T_EQUAL) {
                        if (($tokens[($nextToken - 1)]['code']) !== T_WHITESPACE) {
                            $error = 'Expected 1 space before = sign of default value';
                            $fix   = $phpcsFile->addFixableError($error, $nextToken, 'NoSpaceBeforeEquals');
                            if ($fix === true) {
                                $phpcsFile->fixer->addContentBefore($nextToken, ' ');
                            }
                        }

                        if ($tokens[($nextToken + 1)]['code'] !== T_WHITESPACE) {
                            $error = 'Expected 1 space after = sign of default value';
                            $fix   = $phpcsFile->addFixableError($error, $nextToken, 'NoSpaceAfterEquals');
                            if ($fix === true) {
                                $phpcsFile->fixer->addContent($nextToken, ' ');
                            }
                        }
                    }
                }
            }//end if
        }//end while

    }//end process()


}//end class