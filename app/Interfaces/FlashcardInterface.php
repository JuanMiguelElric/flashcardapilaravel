<?php
namespace App\Interfaces;
interface FlashcardInterface{
    public function addFlashCard( $flashCard, $flashcardForGrafo);
    public function removeFlashCard( $flashCard);


}