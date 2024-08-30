import { get, patch, post, remove } from './base'

export function getQuizStatus (quizId) {
  return get(`/user/current/quizsessions/${quizId}/status`)
}

export function getQuizResults (quizId) {
  return get(`/user/current/quizsessions/${quizId}/results`)
}

export function getQuestion (quizId) {
  return get(`/user/current/quizsessions/${quizId}/question`)
}

export function startQuiz (quizId, isTimed = true) {
  return post(`/user/current/quizsessions/${quizId}/start${isTimed ? '?isTimed' : ''}`)
}

export function answerQuestion (quizId, selectedAnswers) {
  return post(`/user/current/quizsessions/${quizId}/answer`, selectedAnswers)
}

export function confirmQuiz (quizId) {
  return post(`/user/current/quizsessions/${quizId}/confirm`)
}

export function getQuizSessionHistory (foodsaverId) {
  return get(`/quiz/sessions/${foodsaverId}`)
}

export function deleteQuizSession (sessionId) {
  return remove(`/quiz/session/${sessionId}`)
}

export function getQuiz (quizId) {
  return get(`/quiz/${quizId}`)
}

export function getQuestions (quizId) {
  return get(`/quiz/${quizId}/questions`)
}

export function editQuiz (quizId, data) {
  return patch(`/quiz/${quizId}`, data)
}

export function editQuestion (quizId, questionId, data) {
  return patch(`/quiz/${quizId}/questions/${questionId}`, data)
}

export function editAnswer (quizId, questionId, answerId, data) {
  return patch(`/quiz/${quizId}/questions/${questionId}/answers/${answerId}`, data)
}

export function deleteQuestion (quizId, questionId) {
  return remove(`/quiz/${quizId}/questions/${questionId}`)
}

export function deleteAnswer (quizId, questionId, answerId) {
  return remove(`/quiz/${quizId}/questions/${questionId}/answers/${answerId}`)
}

export function addAnswer (quizId, questionId, data) {
  return post(`/quiz/${quizId}/questions/${questionId}/answers`, data)
}

export function addQuestion (quizId, data) {
  return post(`/quiz/${quizId}/questions`, data)
}
